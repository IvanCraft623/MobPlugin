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
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\world\BlockGetter;
use IvanCraft623\Pathfinder\world\Terrain;
use IvanCraft623\Pathfinder\world\WorldBlockGetter;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\promise\Promise;
use pocketmine\world\World;
use function abs;
use function floor;

/**
 * Takes a mob where its goals send it. How it moves is its {@link Locomotion}, what it may do
 * its {@link TraversalRules}.
 */
final class PathNavigation {

	public const STUCK_CHECK_INTERVAL = 100;
	public const STUCK_THRESHOLD_DISTANCE_FACTOR = 0.25;

	private ?Path $path = null;

	private float $speedModifier = 1.0;

	private int $tick = 0;

	private int $lastStuckCheck = 0;

	private Vector3 $lastStuckCheckPos;

	private Vector3 $timeoutCachedNode;

	private int $timeoutTimer = 0;

	private int $lastTimeoutCheck = 0;

	private float $timeoutLimit = 0;

	private NodeEvaluator $evaluator;

	private ?WorldBlockGetter $blockGetter = null;

	private int $maxVisitedNodes;

	/** Position handed to the move control for node $wantedNodeIndex of $wantedPath */
	private ?Vector3 $wantedPosition = null;
	private ?Path $wantedPath = null;
	private int $wantedNodeIndex = -1;

	private TraversalRules $rules;

	private PathRequester $requester;

	private EntityRepathThrottle $entityThrottle;

	public function __construct(
		private Mob $mob,
		private Locomotion $locomotion,
		?TraversalRules $rules = null
	) {
		$this->rules = $rules ?? new TraversalRules();
		$this->evaluator = $locomotion->createEvaluator();
		$this->maxVisitedNodes = (int) floor($mob->getFollowRange() * 16);
		$this->lastStuckCheckPos = Vector3::zero();
		$this->resetStuckTimeout();

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
				$this->maxVisitedNodes
			),
			onSearchStart: $this->resetStuckTimeout(...)
		);
	}

	public function getRules() : TraversalRules{
		return $this->rules;
	}

	public function getLocomotion() : Locomotion{
		return $this->locomotion;
	}

	/**
	 * Changes the way the mob moves; whatever it was doing is dropped.
	 */
	public function setLocomotion(Locomotion $locomotion) : void{
		if ($locomotion === $this->locomotion) {
			return;
		}
		$this->stop();
		$this->locomotion = $locomotion;
		$this->evaluator = $locomotion->createEvaluator();
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
			$this->resetStuckTimeout();
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

	private function startFollowing(?Path $path, float $speedModifier) : bool{
		if ($path === null) {
			$this->path = null;
			return false;
		}

		if ($path !== $this->path) {
			if ($this->path === null || !$path->equals($this->path)) {
				$this->path = $path;
				$this->resetStuckTimeout();
			}
		} elseif (!$this->isDone()) {
			//Already following it, and adjusted
			$this->speedModifier = $speedModifier;
			$this->lastStuckCheckPos = $this->locomotion->pathPosition($this->mob, $this->rules);
			$this->lastStuckCheck = $this->tick;

			return $path->getNodeCount() > 0;
		}

		if ($this->isDone()) {
			return false;
		}

		$path = $this->path;
		if ($path === null) {
			return false;
		}
		$this->locomotion->adjustPath($this->mob, $path, $this->rules);
		if ($path->getNodeCount() <= 0) {
			return false;
		}

		$this->speedModifier = $speedModifier;
		$this->lastStuckCheckPos = $this->locomotion->pathPosition($this->mob, $this->rules);
		$this->lastStuckCheck = $this->tick;

		return true;
	}

	public function getPath() : ?Path{
		return $this->path;
	}

	public function tick() : void{
		$this->tick++;

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
			$this->followThePath();
		} else {
			$this->locomotion->advanceWithoutFollowing($this->mob, $path, $this->locomotion->pathPosition($this->mob, $this->rules));
		}

		//followThePath() (or the async computation) may have stopped/invalidated the path.
		$path = $this->path;
		if ($path !== null && !$path->isDone()) {
			$nodeIndex = $path->getNextNodeIndex();
			if ($this->wantedPosition === null || $path !== $this->wantedPath || $nodeIndex !== $this->wantedNodeIndex) {
				$nextPos = $path->getNextNodeCenter();
				$this->wantedPosition = new Vector3($nextPos->x, $this->locomotion->wantedY($this->mob, $this->getBlockGetter(), $nextPos), $nextPos->z);
				$this->wantedPath = $path;
				$this->wantedNodeIndex = $nodeIndex;
			}
			$this->mob->getMoveControl()->setWantedPosition($this->wantedPosition, $this->speedModifier);
		}
	}

	public function onBlockChanged(Vector3 $position) : void{
		$path = $this->path;
		if ($path === null || $path->isDone() || !$this->requester->invalidate($path, $position->getFloorX(), $position->getFloorY(), $position->getFloorZ())) {
			return;
		}

		//The ground under the node being walked to may not be where it was
		$this->wantedPosition = null;
		$this->recomputePath();
	}

	private function followThePath() : void{
		if ($this->path === null || $this->path->isDone()) {
			return;
		}

		$tempPos = $this->locomotion->pathPosition($this->mob, $this->rules);
		$width = $this->mob->getSize()->getWidth();
		$maxDistanceToWaypoint = $width > 0.75 ? $width / 2 : 0.75 - $width / 2;
		$nodePos = $this->path->getNextNodePos();
		$mobPos = $this->mob->getPosition();

		$dx = abs($mobPos->getX() - ($nodePos->getX() + 0.5));
		$dy = abs($mobPos->getY() - $nodePos->getY());
		$dz = abs($mobPos->getZ() - ($nodePos->getZ() + 0.5));

		if (($dx < $maxDistanceToWaypoint && $dz < $maxDistanceToWaypoint && $dy < 1) ||
			($this->locomotion->canCutCorner($this->path->getNextNode()->type) && $this->shouldTargetNextNodeInDirection($tempPos))
		) {
			$this->path->advance();
		}

		$this->doStuckDetection($tempPos);
	}

	private function shouldTargetNextNodeInDirection(Vector3 $direction) : bool{
		if ($this->path === null) {
			return false;
		}

		if ($this->path->getNextNodeIndex() + 1 >= $this->path->getNodeCount()) {
			return false;
		}

		$nodePos = $this->path->getNextNodePos()->add(0.5, 0, 0.5);

		if ($direction->distanceSquared($nodePos) > 4) {
			return false;
		}
		if ($this->locomotion->canMoveDirectly($this->mob, $direction, $this->path->getNextNodeCenter())) {
			return true;
		}

		$nextNodePos = $this->path->getNodePos($this->path->getNextNodeIndex() + 1)->add(0.5, 0, 0.5);

		$currentNodeToDirection = $nodePos->subtractVector($direction);
		$nextNodeToDirection = $nextNodePos->subtractVector($direction);

		$currentNodeToDirectionLengthSqr = $currentNodeToDirection->lengthSquared();
		$nextNodeToDirectionLengthSqr = $nextNodeToDirection->lengthSquared();

		$nextIsCloser = $nextNodeToDirectionLengthSqr < $currentNodeToDirectionLengthSqr;
		$currentIsVeryClose = $currentNodeToDirectionLengthSqr < 0.5;

		if (!$nextIsCloser && !$currentIsVeryClose) {
			return false;
		}

		return $nextNodeToDirection->normalize()->dot($currentNodeToDirection->normalize()) < 0;
	}

	private function doStuckDetection(Vector3 $position) : void{
		$mobSpeed = $this->mob->getMotionSpeed();
		if ($this->tick - $this->lastStuckCheck > self::STUCK_CHECK_INTERVAL) {
			$speed = $mobSpeed >= 1 ? $mobSpeed : $mobSpeed ** 2;
			if ($position->distanceSquared($this->lastStuckCheckPos) < (($speed * 100 * self::STUCK_THRESHOLD_DISTANCE_FACTOR) ** 2)) {
				$this->stop();
			}

			$this->lastStuckCheck = $this->tick;
			$this->lastStuckCheckPos = $position;
		}

		if ($this->path !== null && !$this->path->isDone()) {
			$nextNodePos = $this->path->getNextNodePos();
			$time = $this->mob->getWorld()->getTime();

			if ($nextNodePos->equals($this->timeoutCachedNode)) {
				$this->timeoutTimer += $time - $this->lastTimeoutCheck;
			} else {
				$this->timeoutCachedNode = $nextNodePos;
				$distanceToNextNode = $position->distance($this->timeoutCachedNode->add(0.5, 0, 0.5));
				$this->timeoutLimit = $mobSpeed > 0 ? $distanceToNextNode / $mobSpeed * 20 : 0;
			}

			if ($this->timeoutLimit > 0 && $this->timeoutTimer > $this->timeoutLimit * 3) {
				$this->timeoutPath();
			}

			$this->lastTimeoutCheck = $time;
		}
	}

	private function timeoutPath() : void{
		$this->resetStuckTimeout();
		$this->stop();
	}

	private function resetStuckTimeout() : void{
		$this->timeoutCachedNode = Vector3::zero();
		$this->timeoutTimer = 0;
		$this->timeoutLimit = 0;
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
		$this->wantedPosition = null;
		$this->wantedPath = null;
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
		return $this->evaluator->getPathTypeResolver()->getBlockPathType($this->getBlockGetter(), $x, $y, $z, $this->mob->getPosition());
	}
}
