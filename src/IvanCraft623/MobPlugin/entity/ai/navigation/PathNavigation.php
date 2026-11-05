<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\navigation;

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\entity\ai\navigation\locomotion\Locomotion;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\world\BlockGetter;
use IvanCraft623\Pathfinder\world\Terrain;
use IvanCraft623\Pathfinder\world\WorldBlockGetter;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\promise\Promise;
use pocketmine\world\World;
use function floor;

/**
 * Takes a mob where its goals send it. How it moves is its {@link Locomotion}, what it may do
 * its {@link TraversalRules}.
 */
final class PathNavigation {

	private ?Path $path = null;

	private float $speedModifier = 1.0;

	private int $tick = 0;

	private NodeEvaluator $evaluator;

	private ?WorldBlockGetter $blockGetter = null;

	private TraversalRules $rules;

	private BlockPathTypeCostMap $costMap;

	private PathFollower $follower;

	private PathRequester $requester;

	private ?Vector3 $wantedPosition = null;

	private EntityRepathThrottle $entityThrottle;

	public function __construct(
		private PathfinderMob $mob,
		private Locomotion $locomotion,
		?TraversalRules $rules = null
	) {
		$this->rules = $rules ?? new TraversalRules();
		$this->costMap = new BlockPathTypeCostMap();
		$this->evaluator = $locomotion->createEvaluator();
		$this->follower = new PathFollower($mob);

		$this->entityThrottle = new EntityRepathThrottle();
		$this->requester = new PathRequester(
			MobPlugin::getInstance()->getPathSearchExecutor(),
			new RepathPolicy(),
			currentPath: fn() : ?Path => $this->path,
			canSearch: fn() : bool => $this->mob->getPosition()->getY() >= $this->mob->getWorld()->getMinY() &&
				$this->locomotion->canFollowPath($this->mob, $this->rules),
			createSetup: fn() : SearchSetup => new SearchSetup(
				$this->evaluator,
				$this->locomotion->createProfile($this->mob, $this->rules),
				Terrain::fromWorld($this->mob->getWorld()),
				$this->mob->getPosition(),
				$this->mob->isOnGround(),
				$this->mob->getFollowRange(),
				(int) floor($this->mob->getFollowRange() * 16)
			),
			onSearchStart: $this->follower->resetNodeProgress(...)
		);
	}

	public function getRules() : TraversalRules{
		return $this->rules;
	}

	/**
	 * What the mob pays to path through each kind of block.
	 */
	public function getCostMap() : BlockPathTypeCostMap{
		return $this->costMap;
	}

	public function getPathfindingMalus(BlockPathType $pathType) : float{
		// TODO: vehicle checks
		return $this->costMap->getPathfindingMalus($pathType);
	}

	public function setPathfindingMalus(BlockPathType $pathType, float $malus) : void{
		$this->costMap->setPathfindingMalus($pathType, $malus);
	}

	/**
	 * Where the mob goes next, set by tick(). Null without a path left to walk.
	 */
	public function getWantedPosition() : ?Vector3{
		return $this->wantedPosition;
	}

	public function getSpeedModifier() : float{
		return $this->speedModifier;
	}

	public function getLocomotion() : Locomotion{
		return $this->locomotion;
	}

	public function isPathComputationPending() : bool{
		return $this->requester->isPending();
	}

	private function recomputeIfDelayed() : void{
		if ($this->requester->hasDelayedRecompute()) {
			$this->recomputePath();
		}
	}

	private function recomputePath() : void{
		$this->requester->recompute($this->path, $this->mob->getWorld()->getServer()->getTick(), function() : void{
			$this->path = null;
		})?->onCompletion(function(Path $path) : void{
			$this->path = $path;
			$this->follower->resetNodeProgress();
		}, function() : void{
			$this->path = null;
		});
	}

	/**
	 * @phpstan-return Promise<Path>
	 */
	private function requestPathTo(Vector3 $position, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		CustomTimings::$pathRequest->startTiming();
		$promise = $this->requester->request($this->locomotion->adjustTarget($this->mob, $position), $reach, $this->path, $maxDistanceFromStart);
		CustomTimings::$pathRequest->stopTiming();

		return $promise;
	}

	public function moveToXYZ(float $x, float $y, float $z, float $speedModifier, int $reach = 1) : void{
		$this->entityThrottle->reset();
		$target = new Vector3($x, $y, $z);
		$this->locomotion->onMoveRequested($target, $speedModifier);
		$this->requestPathTo($target, $reach)->onCompletion(function(Path $path) use ($speedModifier){
			$this->startFollowing($path, $speedModifier);
		}, static function(){});
	}

	public function moveToEntity(Entity $target, float $speedModifier, int $reach = 1) : void{
		$targetPosition = $target->getPosition();
		$this->locomotion->onMoveRequested($targetPosition, $speedModifier);

		$targetBlock = World::blockHash((int) floor($targetPosition->x), (int) floor($targetPosition->y), (int) floor($targetPosition->z));
		$pending = $this->isPathComputationPending();
		if (!$this->entityThrottle->shouldRequest($targetBlock, $pending, $this->path, $this->tick)) {
			$this->speedModifier = $speedModifier;
			return;
		}
		$this->entityThrottle->requested($targetBlock, $pending, $this->tick);

		$this->requestPathTo($targetPosition, $reach)->onCompletion(function(Path $path) use ($speedModifier, $targetBlock) : void{
			$this->startFollowing($path, $speedModifier);
			$this->entityThrottle->resolved($targetBlock, $this->path);
		}, function() : void{
			$this->entityThrottle->reset();
		});
	}

	/**
	 * Follows $path, unless it is (or equals) the current one.
	 */
	private function startFollowing(Path $path, float $speedModifier) : void{
		if ($path !== $this->path) {
			if ($this->path === null || !$path->equals($this->path)) {
				$this->path = $path;
				$this->follower->resetNodeProgress();
			}
		} elseif (!$this->isDone()) {
			//Already following it, and adjusted
			$this->speedModifier = $speedModifier;
			$this->follower->restartStuckCheck($this->tick, $this->locomotion->pathPosition($this->mob, $this->rules));
			return;
		}

		if ($this->isDone()) {
			return;
		}

		$this->locomotion->adjustPath($this->mob, $this->path, $this->rules);
		$this->speedModifier = $speedModifier;
		$this->follower->restartStuckCheck($this->tick, $this->locomotion->pathPosition($this->mob, $this->rules));
	}

	public function getPath() : ?Path{
		return $this->path;
	}

	public function tick() : void{
		CustomTimings::$navigation->startTiming();
		$this->advance();
		CustomTimings::$navigation->stopTiming();
	}

	private function advance() : void{
		$this->tick++;
		$this->wantedPosition = null;

		$this->recomputeIfDelayed();

		//Walked, or still being computed
		$path = $this->path;
		if ($path === null || $path->isDone()) {
			if ($this->isDone()) {
				$this->locomotion->tickWithoutPath($this->mob);
			}
			return;
		}

		if ($this->locomotion->canFollowPath($this->mob, $this->rules)) {
			match ($this->follower->follow($path, $this->locomotion, $this->rules, $this->tick)) {
				//Not where the path thinks it is: search again from where the mob is
				StuckResult::BLOCKED => $this->recomputePath(),
				StuckResult::STUCK, StuckResult::TIMED_OUT => $this->stop(),
				StuckResult::NONE => null
			};
		} else {
			$this->locomotion->advanceWithoutFollowing($this->mob, $path, $this->locomotion->pathPosition($this->mob, $this->rules));
		}

		//Following may have dropped the path
		$path = $this->path;
		if ($path !== null && !$path->isDone()) {
			$this->wantedPosition = $this->follower->getWantedPosition($path, $this->locomotion, $this->getBlockGetter());
		}
	}

	public function onBlockChanged(Vector3 $position) : void{
		$path = $this->path;
		if ($path === null || $path->isDone() || !$this->requester->invalidate($path, $position->getFloorX(), $position->getFloorY(), $position->getFloorZ())) {
			return;
		}

		$this->follower->forgetWantedPosition();
		$this->recomputePath();
	}

	public function isDone() : bool{
		//Not done while a path is being computed, or the goal would stop and cancel it
		return ($this->path === null || $this->path->isDone()) && !$this->isPathComputationPending();
	}

	public function isInProgress() : bool{
		return !$this->isDone();
	}

	public function stop() : void{
		$this->path = null;
		$this->entityThrottle->reset();
		$this->follower->forgetWantedPosition();
		$this->locomotion->onStopped();

		$this->requester->cancel();
	}

	public function isInLiquid() : bool{
		return Locomotion::isInLiquid($this->mob);
	}

	public function isStableDestination(Vector3 $position) : bool{
		return $this->locomotion->isStableDestination($this->mob, $position);
	}

	/**
	 * Reads the mob's world on the main thread.
	 */
	public function getBlockGetter() : BlockGetter{
		$world = $this->mob->getWorld();
		if ($this->blockGetter === null || $this->blockGetter->getWorld() !== $world) {
			$this->blockGetter = new WorldBlockGetter($world);
		}
		return $this->blockGetter;
	}

	/**
	 * Type of the block for the locomotion's evaluator.
	 */
	public function getBlockPathType(int $x, int $y, int $z) : BlockPathType{
		return $this->evaluator->getPathTypeResolver()->getBlockPathType($this->getBlockGetter(), $x, $y, $z);
	}
}
