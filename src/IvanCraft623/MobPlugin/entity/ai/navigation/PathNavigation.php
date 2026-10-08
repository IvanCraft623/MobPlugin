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
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\evaluator\EntityNodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathFinder;
use IvanCraft623\Pathfinder\task\AsyncPathFinderTask;
use IvanCraft623\Pathfinder\world\SyncBlockGetter;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\FillableCauldron;
use pocketmine\block\Liquid;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\world\World;
use function abs;
use function floor;

abstract class PathNavigation {

	public const MAX_TIME_RECOMPUTE = 20;
	public const STUCK_CHECK_INTERVAL = 100;
	public const STUCK_THRESHOLD_DISTANCE_FACTOR = 0.25;

	public const DEFAULT_MAX_VISITED_NODES_MULTIPLIER = 1.0;

	/** Ticks before asking again for a path to an entity that has not changed block */
	public const ENTITY_REPATH_INTERVAL = 20;

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

	protected float $maxDistanceToWaypoint = 0.5;

	protected bool $hasDelayedRecomputation = false;

	protected int $timeLastRecompute = 0;

	protected EntityNodeEvaluator $nodeEvaluator;

	protected int $maxVisitedNodes;

	protected ?Vector3 $targetPosition = null;

	/** Position handed to the move control for node $wantedNodeIndex of $wantedPath */
	private ?Vector3 $wantedPosition = null;
	private ?Path $wantedPath = null;
	private int $wantedNodeIndex = -1;

	/** Block of the entity last requested through moveToEntity(), while that request stands */
	private ?int $entityTargetBlock = null;

	/** Path that request resolved to */
	private ?Path $entityTargetPath = null;

	/** Navigation tick of that request */
	private int $entityTargetTick = 0;

	protected int $reachRange;

	protected float $maxVisitedNodesMultiplier = self::DEFAULT_MAX_VISITED_NODES_MULTIPLIER;

	protected PathFinder $pathfinder;

	protected bool $isStuck = false;

	private int $pathComputationId = 0;

	/**
	 * Of the in-flight path computation; requests made meanwhile share its promise.
	 *
	 * @phpstan-var PromiseResolver<Path>|null
	 */
	private ?PromiseResolver $pendingResolver = null;

	private ?AsyncPathFinderTask $pendingTask = null;

	public function __construct(Mob $mob) {
		$this->mob = $mob;
		$this->maxVisitedNodes = (int) floor($this->mob->getFollowRange() * 16);
		$this->createPathFinder();
	}

	public function getWorld() : World{
		return $this->mob->getWorld();
	}

	public function resetMaxVisitedNodesMultiplier() : void{
		$this->maxVisitedNodesMultiplier = self::DEFAULT_MAX_VISITED_NODES_MULTIPLIER;
	}

	public function setMaxVisitedNodesMultiplier(float $value) : void{
		$this->maxVisitedNodesMultiplier = $value;
	}

	public function getTargetPosition() : ?Vector3{
		return $this->targetPosition;
	}

	public function isPathComputationPending() : bool{
		return $this->pendingResolver !== null;
	}

	protected abstract function createPathFinder() : void;

	public function setSpeedModifier(float $speed) : void{
		$this->speedModifier = $speed;
	}

	public function recomputePath() : void{
		$time = $this->getWorld()->getServer()->getTick();

		if ($time - $this->timeLastRecompute <= self::MAX_TIME_RECOMPUTE) {
			$this->hasDelayedRecomputation = true;
			return;
		}

		if ($this->targetPosition === null) {
			return;
		}

		$this->timeLastRecompute = $time;
		$this->hasDelayedRecomputation = false;

		$targetPosition = clone $this->targetPosition;
		$reachRange = $this->reachRange;

		//Drop the current path, otherwise createPath() hands it back for the unchanged target.
		$this->path = null;

		//createPath() owns the path computation id and the pending state; if a computation is already
		//running it will attach to it instead of starting a duplicate one.
		$this->createPath($targetPosition, $reachRange)->onCompletion(
			function(Path $path) : void{
				$this->path = $path;
				$this->resetStuckTimeout();
			},
			function() : void{
				$this->path = null;
			}
		);
	}

	/**
	 * @phpstan-return Promise<Path>
	 */
	public function createPathToXYZ(float $x, float $y, float $z, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		return $this->createPathToPosition(new Vector3($x, $y, $z), $reach, $maxDistanceFromStart);
	}

	/**
	 * @phpstan-return Promise<Path>
	 */
	public function createPathToEntity(Entity $target, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		return $this->createPathToPosition($target->getPosition(), $reach, $maxDistanceFromStart);
	}

	/**
	 * @phpstan-return Promise<Path>
	 */
	public function createPathToPosition(Vector3 $position, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		return $this->createPath($position, $reach, $maxDistanceFromStart);
	}

	/**
	 * @phpstan-return Promise<Path>
	 */
	public function createPath(Vector3 $position, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		/** @phpstan-var PromiseResolver<Path> $pathResolver */
		$pathResolver = new PromiseResolver();
		if ($this->mob->getPosition()->getY() < World::Y_MIN) {
			$pathResolver->reject();
			return $pathResolver->getPromise();
		}
		if (!$this->canUpdatePath()) {
			$pathResolver->reject();
			return $pathResolver->getPromise();
		}

		if ($this->targetPosition !== null &&
			$this->path !== null &&
			!$this->path->isDone() &&
			$this->sameBlock($this->targetPosition, $position)
		) {
			$pathResolver->resolve($this->path);
			return $pathResolver->getPromise();
		}

		if ($this->pendingResolver !== null) {
			//A computation is already running; don't stack another one (each submission synchronously
			//re-serializes the whole start->target chunk corridor on the main thread). Attach to the
			//in-flight promise instead: everyone gets the same result and, if it's stale for a caller,
			//that caller will simply re-request on the next tick.
			$this->pendingResolver->getPromise()->onCompletion(
				static function(Path $path) use ($pathResolver) : void{
					$pathResolver->resolve($path);
				},
				static function() use ($pathResolver) : void{
					$pathResolver->reject();
				}
			);
			return $pathResolver->getPromise();
		}

		CustomTimings::$pathRequest->startTiming();

		$this->resetStuckTimeout();
		$this->updateNodeEvaluatorAttributes();

		$requestId = ++$this->pathComputationId;
		$this->pendingResolver = $pathResolver;

		//The task outlives a stopped navigation, so it must not keep the navigation (and its mob) alive.
		$navigation = \WeakReference::create($this);
		$this->pendingTask = PathFinder::findPathAsync(static function(Path $path) use ($navigation, $reach, $requestId) : void{
				$navigation->get()?->onPathComputed($path, $reach, $requestId);
			},
			$this->nodeEvaluator,
			$this->mob->getWorld(),
			$this->mob->getPosition(),
			$position,
			(int) floor($this->maxVisitedNodes * $this->maxVisitedNodesMultiplier),
			$maxDistanceFromStart ?? $this->mob->getFollowRange(),
			$reach
		);

		CustomTimings::$pathRequest->stopTiming();

		return $pathResolver->getPromise();
	}

	private function onPathComputed(Path $path, int $reach, int $requestId) : void{
		if ($requestId !== $this->pathComputationId || $this->pendingResolver === null) {
			//Stale result: the navigation was stopped since this computation was requested.
			return;
		}

		$pathResolver = $this->pendingResolver;
		$this->pendingResolver = null;
		$this->pendingTask = null;

		$this->targetPosition = $this->toBlockVector($path->getTarget());
		$this->reachRange = $reach;

		//todo!
		$pathResolver->resolve($path);
	}

	protected function toBlockVector(Vector3 $position) : Vector3{
		return $position->floor();
	}

	protected function sameBlock(Vector3 $a, Vector3 $b) : bool{
		return (int) floor($a->getX()) === (int) floor($b->getX()) &&
			(int) floor($a->getY()) === (int) floor($b->getY()) &&
			(int) floor($a->getZ()) === (int) floor($b->getZ());
	}

	protected function updateNodeEvaluatorAttributes() : void{
		$this->nodeEvaluator->setEntitySize($this->mob->getSize());
		$this->nodeEvaluator->setEntityBoundingBox(clone $this->mob->getBoundingBox());
		$this->nodeEvaluator->setEntityOnGround($this->mob->isOnGround());
		$this->nodeEvaluator->setMaxUpStep($this->mob->getStepHeight());
		$this->nodeEvaluator->setMaxFallDistance($this->mob->getMaxFallDistance());
	}

	public function moveToXYZ(float $x, float $y, float $z, float $speedModifier, int $reach = 1) : void{
		$this->entityTargetBlock = null;
		$this->createPathToXYZ($x, $y, $z, $reach)->onCompletion(function(Path $path) use ($speedModifier){
			$this->moveToPath($path, $speedModifier);
		}, static function(){});
	}

	public function moveToEntity(Entity $target, float $speedModifier, int $reach = 1) : void{
		$targetPosition = $target->getPosition();
		$targetBlock = World::blockHash((int) floor($targetPosition->x), (int) floor($targetPosition->y), (int) floor($targetPosition->z));
		if ($targetBlock === $this->entityTargetBlock) {
			//Goals following an entity ask again every tick; there is nothing new to request until it changes block.
			if ($this->isPathComputationPending()) {
				return;
			}
			if ($this->path !== null && $this->path === $this->entityTargetPath && !$this->path->isDone()) {
				$this->speedModifier = $speedModifier;
				return;
			}
			//The path ended and the entity is still there: a new one is only worth it now and then.
			if ($this->tick - $this->entityTargetTick < self::ENTITY_REPATH_INTERVAL) {
				return;
			}
		}

		//A request made while another computation is in flight gets that computation's path, not its own.
		$this->entityTargetBlock = $this->isPathComputationPending() ? null : $targetBlock;
		$this->entityTargetPath = null;
		$this->entityTargetTick = $this->tick;

		$this->createPathToPosition($targetPosition, $reach)->onCompletion(function(Path $path) use ($speedModifier, $targetBlock) : void{
			$this->moveToPath($path, $speedModifier);
			if ($this->entityTargetBlock === $targetBlock) {
				$this->entityTargetPath = $this->path;
			}
		}, function() : void{
			$this->entityTargetBlock = null;
		});
	}

	public function moveToPath(?Path $path, float $speedModifier) : bool{
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

		if ($this->hasDelayedRecomputation) {
			$this->recomputePath();
		}

		//Either the current path has been fully walked, or an async computation is still in flight
		//(path === null). There is nothing followable yet; wait for the path to arrive.
		$path = $this->path;
		if ($path === null || $path->isDone()) {
			return;
		}

		if ($this->canUpdatePath()) {
			$this->followThePath();
		} else {
			$tempPos = $this->getTempMobPosition();
			$nextPos = $path->getNextEntityPosition($this->mob);
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
				$nextPos = $path->getNextEntityPosition($this->mob);
				$this->wantedPosition = new Vector3($nextPos->x, $this->getGroundY($nextPos), $nextPos->z);
				$this->wantedPath = $path;
				$this->wantedNodeIndex = $nodeIndex;
			}
			$this->mob->getMoveControl()->setWantedPosition($this->wantedPosition, $this->speedModifier);
		}
	}

	public function onBlockChanged(Vector3 $position) : void{
		$path = $this->path;
		if ($path === null || $path->isDone() || !$path->isInCorridor($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())) {
			return;
		}

		//The ground under the node being walked to may not be where it was
		$this->wantedPosition = null;
		$this->recomputePath();
	}

	protected function getGroundY(Vector3 $position) : float{
		return $this->getWorld()->getBlock($position->down())->getTypeId() === BlockTypeIds::AIR ? $position->y : WalkNodeEvaluator::getFloorLevelAt(new SyncBlockGetter($this->getWorld()), $position);
	}

	protected function followThePath() : void{
		if ($this->path === null || $this->path->isDone()) {
			return;
		}

		$tempPos = $this->getTempMobPosition();
		$width = $this->mob->getSize()->getWidth();
		$this->maxDistanceToWaypoint = $width > 0.75 ? $width / 2 : 0.75 - $width / 2;
		$nodePos = $this->path->getNextNodePos();
		$mobPos = $this->mob->getPosition();

		$dx = abs($mobPos->getX() - ($nodePos->getX() + 0.5));
		$dy = abs($mobPos->getY() - $nodePos->getY());
		$dz = abs($mobPos->getZ() - ($nodePos->getZ() + 0.5));

		if (($dx < $this->maxDistanceToWaypoint && $dz < $this->maxDistanceToWaypoint && $dy < 1) ||
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
		if ($this->canMoveDirectly($direction, $this->path->getNextEntityPosition($this->mob))) {
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

	public function doStuckDetection(Vector3 $position) : void{
		$mobSpeed = $this->mob->getMotionSpeed();
		if ($this->tick - $this->lastStuckCheck > self::STUCK_CHECK_INTERVAL) {
			$speed = $mobSpeed >= 1 ? $mobSpeed : $mobSpeed ** 2;
			if ($position->distanceSquared($this->lastStuckCheckPos) < (($speed * 100 * self::STUCK_THRESHOLD_DISTANCE_FACTOR) ** 2)) {
				$this->isStuck = true;
				$this->stop();
			} else {
				$this->isStuck = false;
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

	public function timeoutPath() : void{
		$this->resetStuckTimeout();
		$this->stop();
	}

	public function resetStuckTimeout() : void{
		$this->timeoutCachedNode = Vector3::zero();
		$this->timeoutTimer = 0;
		$this->timeoutLimit = 0;
		$this->isStuck = false;
	}

	public function isDone() : bool{
		//While a path computation is in-flight the path is still null, but we must not report "done",
		//or the running goal will stop and cancel its own path before it arrives.
		return ($this->path === null || $this->path->isDone()) && !$this->isPathComputationPending();
	}

	public function isInProgress() : bool{
		return !$this->isDone();
	}

	public function stop() : void{
		$this->path = null;
		$this->hasDelayedRecomputation = false;
		$this->entityTargetBlock = null;
		$this->entityTargetPath = null;
		$this->wantedPosition = null;
		$this->wantedPath = null;

		//Invalidate any in-flight computation so its late result can't resurrect a path after we've
		//stopped, and so a subsequent move can't be hijacked into an obsolete computation's promise.
		$this->pathComputationId++;
		$this->pendingResolver = null;
		$this->pendingTask?->cancel();
		$this->pendingTask = null;
	}

	protected abstract function getTempMobPosition() : Vector3;

	protected abstract function canUpdatePath() : bool;

	protected function trimPath() : void{
		if ($this->path !== null) {
			$world = $this->getWorld();
			for ($i = 0; $i < $this->path->getNodeCount(); $i++) {
				$node = $this->path->getNode($i);
				if ($world->getBlock($node->asVector3()) instanceof FillableCauldron) {
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
		return $this->getWorld()->getBlock($position->down())->isSolid();
	}

	public function getNodeEvaluator() : EntityNodeEvaluator{
		return $this->nodeEvaluator;
	}

	public function canFloat() : bool{
		return $this->nodeEvaluator->canFloat();
	}

	public function setCanFloat(bool $value = true) : void{
		$this->nodeEvaluator->setCanFloat($value);
	}

	public function getMaxDistanceToWaypoint() : float{
		return $this->maxDistanceToWaypoint;
	}

	public function isStuck() : bool{
		return $this->isStuck;
	}
}
