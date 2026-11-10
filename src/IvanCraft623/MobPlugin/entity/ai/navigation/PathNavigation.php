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
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\PathCursor;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\world\BlockGetter;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\world\Terrain;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\world\WorldBlockGetter;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\promise\Promise;
use pocketmine\world\World;
use function floor;

/**
 * Takes one mob along a path: requests searches, keeps the path valid, advances along it and exposes the next
 * position to reach, which the mob's move control steers toward. How the mob moves is its {@link Locomotion},
 * what it may do its {@link TraversalRules}. See docs/navigation.md.
 */
final class PathNavigation {

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
		$this->follower = $follower = new PathFollower($mob);
		$evaluator = $this->evaluator;
		$rules = $this->rules;

		$this->entityThrottle = new EntityRepathThrottle();
		$this->requester = new PathRequester(
			MobPlugin::getInstance()->getPathSearchExecutor(),
			new RepathPolicy(),
			currentCursor: $follower->getCursor(...),
			createSetup: static fn() : SearchSetup => new SearchSetup(
				$evaluator,
				$locomotion->createProfile($mob, $rules),
				Terrain::fromWorld($mob->getWorld()),
				$mob->getPosition(),
				$mob->isOnGround(),
				$mob->getFollowRange(),
				(int) floor($mob->getFollowRange() * 16)
			),
			onSearchStart: $follower->resetNodeProgress(...)
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

	/**
	 * Whether the mob is somewhere a path can be asked for: in the world and in a state to follow it.
	 */
	private function canRequestPath() : bool{
		return $this->mob->getPosition()->getY() >= $this->mob->getWorld()->getMinY() &&
			$this->locomotion->canFollowPath($this->mob, $this->rules);
	}

	private function recomputePath() : void{
		if (!$this->canRequestPath()) {
			$this->requester->postponeRecompute();
			return;
		}

		$this->requester->recompute($this->follower->getCursor(), $this->mob->getWorld()->getServer()->getTick(), $this->dropPath(...))
			?->onCompletion($this->follower->setCursor(...), $this->dropPath(...));
	}

	private function dropPath() : void{
		$this->follower->setCursor(null);
	}

	/**
	 * @phpstan-return Promise<PathCursor>
	 */
	private function requestPathTo(Vector3 $position, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		CustomTimings::$pathRequest->startTiming();
		$promise = $this->requester->request($this->locomotion->adjustTarget($this->mob, $position), $reach, $this->follower->getCursor(), $maxDistanceFromStart);
		CustomTimings::$pathRequest->stopTiming();

		return $promise;
	}

	public function moveToXYZ(float $x, float $y, float $z, float $speedModifier, int $reach = 1) : void{
		$this->entityThrottle->reset();
		$target = new Vector3($x, $y, $z);
		$this->locomotion->onMoveRequested($target, $speedModifier);
		if (!$this->canRequestPath()) {
			return;
		}
		$this->requestPathTo($target, $reach)->onCompletion(
			fn(PathCursor $cursor) => $this->startFollowing($cursor, $speedModifier),
			static function() : void{}
		);
	}

	public function moveToEntity(Entity $target, float $speedModifier, int $reach = 1) : void{
		$targetPosition = $target->getPosition();
		$this->locomotion->onMoveRequested($targetPosition, $speedModifier);
		if (!$this->canRequestPath()) {
			return;
		}

		$targetBlock = World::blockHash((int) floor($targetPosition->x), (int) floor($targetPosition->y), (int) floor($targetPosition->z));
		$pending = $this->isPathComputationPending();
		if (!$this->entityThrottle->shouldRequest($targetBlock, $pending, $this->follower->getCursor(), $this->tick)) {
			$this->speedModifier = $speedModifier;
			return;
		}
		$this->entityThrottle->requested($targetBlock, $pending, $this->tick);

		$this->requestPathTo($targetPosition, $reach)->onCompletion(
			fn(PathCursor $cursor) => $this->followEntityPath($cursor, $speedModifier, $targetBlock),
			$this->entityThrottle->reset(...)
		);
	}

	private function followEntityPath(PathCursor $cursor, float $speedModifier, int $targetBlock) : void{
		$this->startFollowing($cursor, $speedModifier);
		$this->entityThrottle->resolved($targetBlock, $this->follower->getCursor());
	}

	/**
	 * Follows $cursor, unless it is (or equals) the current one.
	 */
	private function startFollowing(PathCursor $cursor, float $speedModifier) : void{
		$current = $this->follower->getCursor();
		if ($cursor !== $current) {
			if ($current === null || !$cursor->getPath()->equals($current->getPath())) {
				$this->follower->setCursor($cursor);
			}
		} elseif (!$this->isDone()) {
			//Already following it, and adjusted
			$this->speedModifier = $speedModifier;
			$this->follower->restartStuckCheck($this->tick, $this->locomotion->pathPosition($this->mob, $this->rules));
			return;
		}

		$current = $this->follower->getCursor();
		if ($current === null || $this->isDone()) {
			return;
		}

		$adjusted = $this->locomotion->adjustPath($this->mob, $current->getPath(), $this->rules);
		if ($adjusted !== $current->getPath()) {
			$this->follower->setCursor($current->withPath($adjusted));
		}
		$this->speedModifier = $speedModifier;
		$this->follower->restartStuckCheck($this->tick, $this->locomotion->pathPosition($this->mob, $this->rules));
	}

	/**
	 * The path being followed, and how far along it the mob is.
	 */
	public function getCursor() : ?PathCursor{
		return $this->follower->getCursor();
	}

	/**
	 * The path being followed, without the progress.
	 */
	public function getPath() : ?Path{
		return $this->follower->getCursor()?->getPath();
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
		$cursor = $this->follower->getCursor();
		if ($cursor === null || $cursor->isDone()) {
			if ($this->isDone()) {
				$this->locomotion->tickWithoutPath($this->mob);
			}
			return;
		}

		if ($this->locomotion->canFollowPath($this->mob, $this->rules)) {
			match ($this->follower->follow($this->locomotion, $this->rules, $this->tick)) {
				//Not where the path thinks it is: search again from where the mob is
				StuckResult::BLOCKED => $this->recomputePath(),
				StuckResult::STUCK, StuckResult::TIMED_OUT => $this->stop(),
				StuckResult::NONE => null
			};
		} else {
			$this->locomotion->advanceWithoutFollowing($this->mob, $cursor, $this->locomotion->pathPosition($this->mob, $this->rules));
		}

		//Following may have dropped the path
		$this->wantedPosition = $this->follower->getWantedPosition($this->locomotion, $this->getBlockGetter());
	}

	public function onBlockChanged(Vector3 $position) : void{
		$cursor = $this->follower->getCursor();
		if ($cursor === null || $cursor->isDone() || !$this->requester->invalidate($cursor, $position->getFloorX(), $position->getFloorY(), $position->getFloorZ())) {
			return;
		}

		$this->follower->forgetWantedPosition();
		$this->recomputePath();
	}

	public function isDone() : bool{
		//Not done while a path is being computed, or the goal would stop and cancel it
		$cursor = $this->follower->getCursor();

		return ($cursor === null || $cursor->isDone()) && !$this->isPathComputationPending();
	}

	public function isInProgress() : bool{
		return !$this->isDone();
	}

	public function stop() : void{
		$this->follower->setCursor(null);
		$this->entityThrottle->reset();
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