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
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\pathtype\BlockClassifier;
use IvanCraft623\Pathfinder\world\BlockGetter;
use IvanCraft623\Pathfinder\world\Terrain;
use IvanCraft623\Pathfinder\world\WorldBlockGetter;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\FillableCauldron;
use pocketmine\block\Liquid;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\promise\Promise;
use pocketmine\world\World;
use function abs;
use function floor;

abstract class PathNavigation {

	public const STUCK_CHECK_INTERVAL = 100;
	public const STUCK_THRESHOLD_DISTANCE_FACTOR = 0.25;

	protected Mob $mob;

	protected ?Path $path = null;

	protected float $speedModifier;

	protected int $tick = 0;

	protected int $lastStuckCheck;

	protected Vector3 $lastStuckCheckPos;

	protected Vector3 $timeoutCachedNode;

	protected int $timeoutTimer;

	protected int $lastTimeoutCheck;

	protected float $timeoutLimit;

	protected NodeEvaluator $nodeEvaluator;

	private ?WorldBlockGetter $blockGetter = null;

	protected bool $canFloat = false;
	protected bool $canOpenDoors = false;
	protected bool $canPassDoors = false;
	protected bool $canWalkOverFences = false;

	protected int $maxVisitedNodes;

	/** Position handed to the move control for node $wantedNodeIndex of $wantedPath */
	private ?Vector3 $wantedPosition = null;
	private ?Path $wantedPath = null;
	private int $wantedNodeIndex = -1;

	private PathRequester $requester;

	private EntityRepathThrottle $entityThrottle;

	public function __construct(Mob $mob) {
		$this->mob = $mob;
		$this->maxVisitedNodes = (int) floor($this->mob->getFollowRange() * 16);
		$this->createPathFinder();

		$this->entityThrottle = new EntityRepathThrottle();
		$this->requester = new PathRequester(
			MobPlugin::getInstance()->getPathSearchExecutor(),
			new RepathPolicy(),
			currentPath: fn() : ?Path => $this->path,
			canSearch: fn() : bool => $this->mob->getPosition()->getY() >= $this->getWorld()->getMinY() && $this->canUpdatePath(),
			createSetup: fn() : SearchSetup => new SearchSetup(
				$this->nodeEvaluator,
				$this->createMovementProfile(),
				Terrain::fromWorld($this->getWorld()),
				$this->mob->getPosition(),
				$this->mob->isOnGround(),
				$this->mob->getFollowRange(),
				$this->maxVisitedNodes
			),
			onSearchStart: $this->resetStuckTimeout(...)
		);
	}

	public function getWorld() : World{
		return $this->mob->getWorld();
	}

	public function isPathComputationPending() : bool{
		return $this->requester->isPending();
	}

	protected abstract function createPathFinder() : void;

	protected function recomputeIfDelayed() : void{
		if ($this->requester->hasDelayedRecompute()) {
			$this->recomputePath();
		}
	}

	protected function recomputePath() : void{
		$this->requester->recompute($this->path, $this->getWorld()->getServer()->getTick(), function() : void{
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
	protected function requestPathTo(Vector3 $position, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		CustomTimings::$pathRequest->startTiming();
		$promise = $this->requester->request($position, $reach, $this->path, $maxDistanceFromStart);
		CustomTimings::$pathRequest->stopTiming();

		return $promise;
	}

	protected function getSearchCostMap() : BlockPathTypeCostMap{
		return $this->mob->getPathTypeCostMap();
	}

	protected function createMovementProfile() : MovementProfile{
		$size = $this->mob->getSize();
		return new MovementProfile(
			width: $size->getWidth(),
			height: $size->getHeight(),
			maxUpStep: $this->mob->getStepHeight(),
			maxFallDistance: $this->mob->getMaxFallDistance(),
			costMap: $this->getSearchCostMap(),
			canPassDoors: $this->canPassDoors,
			canOpenDoors: $this->canOpenDoors,
			canFloat: $this->canFloat,
			canWalkOverFences: $this->canWalkOverFences,
			eyeHeight: $size->getEyeHeight()
		);
	}

	public function moveToXYZ(float $x, float $y, float $z, float $speedModifier, int $reach = 1) : void{
		$this->entityThrottle->reset();
		$this->requestPathTo(new Vector3($x, $y, $z), $reach)->onCompletion(function(Path $path) use ($speedModifier){
			$this->startFollowing($path, $speedModifier);
		}, static function(){});
	}

	public function moveToEntity(Entity $target, float $speedModifier, int $reach = 1) : void{
		$targetPosition = $target->getPosition();
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
			//Already following it, and trimmed
			$this->speedModifier = $speedModifier;
			$this->lastStuckCheckPos = $this->getTempMobPosition();
			$this->lastStuckCheck = $this->tick;

			return $path->getNodeCount() > 0;
		}

		if ($this->isDone()) {
			return false;
		}

		$this->trimPath();

		/**
		 * @var Path $path
		 */
		$path = $this->path;
		if ($path->getNodeCount() <= 0) {
			return false;
		}

		$this->speedModifier = $speedModifier;
		$this->lastStuckCheckPos = $this->getTempMobPosition();
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
			return;
		}

		if ($this->canUpdatePath()) {
			$this->followThePath();
		} else {
			$tempPos = $this->getTempMobPosition();
			$nextPos = $path->getNextNodeCenter();
			if ($tempPos->y > $nextPos->y &&
				!$this->mob->isOnGround() &&
				floor($tempPos->x) === floor($nextPos->x) &&
				floor($tempPos->z) === floor($nextPos->z)
			) {
				$path->advance();
			}
		}

		//followThePath() (or the async computation) may have stopped/invalidated the path.
		$path = $this->path;
		if ($path !== null && !$path->isDone()) {
			$nodeIndex = $path->getNextNodeIndex();
			if ($this->wantedPosition === null || $path !== $this->wantedPath || $nodeIndex !== $this->wantedNodeIndex) {
				$nextPos = $path->getNextNodeCenter();
				$this->wantedPosition = new Vector3($nextPos->x, $this->getGroundY($nextPos), $nextPos->z);
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

	protected function getGroundY(Vector3 $position) : float{
		return $this->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->getTypeId() === BlockTypeIds::AIR ? $position->y : BlockClassifier::getFloorLevel($this->getBlockGetter(), $position);
	}

	protected function followThePath() : void{
		if ($this->path === null || $this->path->isDone()) {
			return;
		}

		$tempPos = $this->getTempMobPosition();
		$width = $this->mob->getSize()->getWidth();
		$maxDistanceToWaypoint = $width > 0.75 ? $width / 2 : 0.75 - $width / 2;
		$nodePos = $this->path->getNextNodePos();
		$mobPos = $this->mob->getPosition();

		$dx = abs($mobPos->getX() - ($nodePos->getX() + 0.5));
		$dy = abs($mobPos->getY() - $nodePos->getY());
		$dz = abs($mobPos->getZ() - ($nodePos->getZ() + 0.5));

		if (($dx < $maxDistanceToWaypoint && $dz < $maxDistanceToWaypoint && $dy < 1) ||
			($this->canCutCorner($this->path->getNextNode()->type) && $this->shouldTargetNextNodeInDirection($tempPos))
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
		if ($this->canMoveDirectly($direction, $this->path->getNextNodeCenter())) {
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
			$time = $this->getWorld()->getTime();

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

		$this->requester->cancel();
	}

	protected abstract function getTempMobPosition() : Vector3;

	protected abstract function canUpdatePath() : bool;

	protected function trimPath() : void{
		if ($this->path !== null) {
			$world = $this->getWorld();
			for ($i = 0; $i < $this->path->getNodeCount(); $i++) {
				$node = $this->path->getNode($i);
				if ($world->getBlockAt($node->x(), $node->y(), $node->z()) instanceof FillableCauldron) {
					$this->path->replaceNode($i, $node->cloneAndMove($node->x(), $node->y() + 1, $node->z()));

					$nextNode = $i + 1 < $this->path->getNodeCount() ? $this->path->getNode($i + 1) : null;
					if ($nextNode !== null && $node->y >= $nextNode->y) {
						$this->path->replaceNode($i + 1, $node->cloneAndMove($nextNode->x(), $node->y() + 1, $nextNode->z()));
					}
				}
			}
		}
	}

	protected function canMoveDirectly(Vector3 $from, Vector3 $to) : bool{
		return false;
	}

	protected function canCutCorner(BlockPathType $pathType) : bool{
		return $pathType !== BlockPathType::DANGER_FIRE &&
			$pathType !== BlockPathType::DANGER_OTHER &&
			$pathType !== BlockPathType::WALKABLE_DOOR;
	}

	public function isInLiquid() : bool{
		//Water and lava don't have collision boxes, so they never show up in getCollisionBlocks().
		return $this->mob->isInWater() || $this->mob->isInLava();
	}

	protected static function isClearForMovementBetween(Mob $mob, Vector3 $from, Vector3 $to, bool $detectLiquids) : bool{
		$to = $to->add(0, $mob->getSize()->getHeight() / 2, 0);

		foreach (VoxelRayTrace::betweenPoints($from, $to) as $pos) {
			$block = $mob->getWorld()->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);

			if ($block instanceof Liquid && !$detectLiquids) {
				continue;
			}

			if ($block->calculateIntercept($from, $to) !== null) {
				return false;
			}
		}

		return true;
	}

	public function isStableDestination(Vector3 $position) : bool{
		return $this->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->isSolid();
	}

	/**
	 * Reads the mob's world on the main thread.
	 */
	public function getBlockGetter() : BlockGetter{
		$world = $this->getWorld();
		if ($this->blockGetter === null || $this->blockGetter->getWorld() !== $world) {
			$this->blockGetter = new WorldBlockGetter($world);
		}
		return $this->blockGetter;
	}

	/**
	 * Type of the block for the locomotion's evaluator.
	 */
	public function getBlockPathType(int $x, int $y, int $z) : BlockPathType{
		return $this->nodeEvaluator->getPathTypeResolver()->getBlockPathType($this->getBlockGetter(), $x, $y, $z, $this->mob->getPosition());
	}

	public function setCanFloat(bool $value = true) : void{
		$this->canFloat = $value;
	}

	public function setCanOpenDoors(bool $value = true) : void{
		$this->canOpenDoors = $value;
	}

	public function setCanPassDoors(bool $value = true) : void{
		$this->canPassDoors = $value;
	}

	public function setCanWalkOverFences(bool $value = true) : void{
		$this->canWalkOverFences = $value;
	}

}
