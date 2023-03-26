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

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;
use IvanCraft623\MobPlugin\pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\MobPlugin\pathfinder\Path;
use IvanCraft623\MobPlugin\pathfinder\PathFinder;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Liquid;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\World;
use function abs;
use function count;
use function floor;

abstract class PathNavigation {

	public const MAX_TIME_RECOMPUTE = 20;
	public const STUCK_CHECK_INTERVAL = 100;
	public const STUCK_THRESHOLD_DISTANCE_FACTOR = 0.25;

	public const DEFAULT_MAX_VISITED_NODES_MULTIPLIER = 1.0;

	protected Mob $mob;

	protected World $world;

	protected ?Path $path;

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

	protected int $timeLastRecompute;

	protected NodeEvaluator $nodeEvaluator;

	protected ?Vector3 $targetPosition;

	protected int $reachRange;

	protected float $maxVisitedNodesMultiplier = self::DEFAULT_MAX_VISITED_NODES_MULTIPLIER;

	protected PathFinder $pathfinder;

	protected bool $isStuck = false;

	public function __construct(Mob $mob, World $world) {
		$this->mob = $mob;
		$this->world = $world;

		$followRange = $this->mob->getAttributeMap()->get(Attribute::FOLLOW_RANGE)?->getValue() ?? throw new AssumptionFailedError("Follow range attribute is null");
		;
		$this->pathfinder = $this->createPathFinder(floor($followRange * 16));
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

	protected abstract function createPathFinder(int $maxVisitedNodes) : PathFinder;

	public function setSpeedModifier(float $speed) : void{
		$this->speedModifier = $speed;
	}

	public function recomputePath() : void{
		if (($time = $this->world->getTime()) - $this->timeLastRecompute > self::MAX_TIME_RECOMPUTE) {
			if ($this->targetPosition !== null) {
				$this->path = null;
				$this->path = $this->createPath($this->targetPosition, $this->reachRange);
				$this->timeLastRecompute = $time;
				$this->hasDelayedRecomputation = false;
			}
		} else {
			$this->hasDelayedRecomputation = true;
		}
	}

	public function createPathFromXYZ(float $x, float $y, float $z, int $maxVisitedNodes, ?float $range = null) : ?Path{
		return $this->createPathFromPosition(new Vector3($x, $y, $z), $maxVisitedNodes, $range);
	}

	public function createPathFromEntity(?Entity $target, int $maxVisitedNodes, ?float $range = null) : ?Path{
		return $this->createPathFromPosition($target->getPosition(), $maxVisitedNodes, $range);
	}

	public function createPathFromPosition(Vector3 $position, int $maxVisitedNodes, ?float $range = null) : ?Path{
		return $this->createPath([$position->floor()], $maxVisitedNodes);
	}

	/**
	 * @param Vector3[] $positions
	 */
	public function createPath(array $positions, int $maxVisitedNodes, ?float $range) : ?Path{
		if (count($positions) === 0) {
			return null;
		}
		if ($this->mob->getPosition()->getY() < World::Y_MIN) {
			return null;
		}
		if (!$this->canUpdatePath()) {
			return null;
		}
		if ($this->targetPosition !== null && $this->path !== null && !$this->path->isDone() && Utils::arrayContains($this->targetPosition, $positions)) {
			return $this->path;
		}

		$range = $range ?? $this->mob->getAttributeMap()->get(Attribute::FOLLOW_RANGE)?->getValue() ?? throw new AssumptionFailedError("Follow range attribute is null");
		$path = $this->pathfinder->findPath($this->world, $this->mob, $positions, $range, $maxVisitedNodes, $this->maxVisitedNodesMultiplier);

		if ($path !== null && ($target = $path->getTarget()) !== null) {
			$this->targetPosition = $target;
			$this->reachRange = $maxVisitedNodes;
			$this->resetStuckTimeout();
		}

		return $path;
	}

	public function moveToXYZ(float $x, float $y, float $z, float $speedModifier) : bool{
		return $this->moveToPath($this->createPathFromXYZ($x, $y, $z, 1), $speedModifier);
	}

	public function moveToEntity(Entity $target, float $speedModifier) : bool{
		$path = $this->createPathFromEntity($target, 1);
		return $path !== null && $this->moveToPath($path, $speedModifier);
	}

	public function moveToPath(?Path $path, float $speedModifier) : bool{
		if ($path === null) {
			$this->path = null;
			return false;
		}

		/**
		 * @var Path $path
		 * @var Path $this->path
		 */
		if (!$path->equals($this->path)) {
			$this->path = $path;
		}
		if ($this->isDone()) {
			return false;
		}

		$this->trimPath();
		if ($this->path->getNodeCount() <= 0) {
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

		if (!$this->isDone()) {
			if ($this->canUpdatePath()) {
				$this->followThePath();
			} elseif ($this->path !== null && !$this->path->isDone()) {
				$tempPos = $this->getTempMobPosition();
				$nextPos = $this->path->getNextEntityPosition($this->mob);
				if ($tempPos->y > $nextPos->y &&
					!$this->mob->isOnGround() &&
					floor($tempPos->x) === floor($nextPos->x) &&
					floor($tempPos->z) === floor($nextPos->z)
				) {
					$this->path->advance();
				}
			}

			if ($this->path !== null && !$this->isDone()) {
				$nextPos = $this->path->getNextEntityPosition($this->mob);
				$nextPos->y = $this->getGroundY($nextPos);
				$this->mob->getMoveControl()->setWantedPosition($nextPos, $this->speedModifier);
			}
		}
	}

	protected function getGroundY(Vector3 $position) : float{
		$blockPos = $position->floor();
		return $this->world->getBlock($position->down())->getId() === BlockLegacyIds::AIR ? $position->y : WalkNodeEvaluator::getFloorLevelAt($this->world, $blockPos);
	}

	protected function followThePath() : void{
		if ($this->path === null) {
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
		if ($this->canMoveDirectly($direction, $this->path->getNextEntityPos($this->mob))) {
			return true;
		}

		$nextNodePos = $this->path->getNodePos($this->path->getNextNodeIndex() + 1)->add(0.5, 0, 0.5);

		$currentNodeToDirection = $nodePos->subtractVector($direction);
		$nextNodeToDirection = $nextNodePos->subtractVector($direction);

		$currentNodeToDirectionLengthSqr = $currentNodeToDirection->lengthSquared();

		if ($nextNodeToDirection->lengthSquared() > $currentNodeToDirectionLengthSqr &&
			$currentNodeToDirectionLengthSqr > 0.5
		) {
			return false;
		}

		return $nextNodeToDirection->normalize()->dot($currentNodeToDirection->normalize()) < 0;
	}

	public function doStuckDetection(Vector3 $position) : void{
		$mobSpeed = $mob->getSpeed();
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
			$time = $this->world->getTime();

			if ($nextNodePos->equals($this->timeoutCachedNode)) {
				$this->timeoutTimer += $time - $this->lastTimeoutCheck;
			} else {
				$this->timeoutCachedNode = $nextNodePos;
				$distanceToNextNode = $position->distance($this->timeoutCachedNode->add(0.5, 0, 0.5));
				$this->timeoutLimit = $mobSpeed > 0 ? $distanceToNextNode / $speed * 20 : 0;
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
		return $this->path === null || $this->path->isDone();
	}

	public function isInProgress() : bool{
		return !$this->isDone();
	}

	public function stop() : void{
		$this->path = null;
	}

	protected abstract function getTempMobPosition() : Vector3;

	protected abstract function canUpdatePath() : bool;

	protected function isInLiquid() : bool{
		foreach ($this->world->getCollisionBlocks($this->mob->getBoundingBox()) as $block) {
			if ($block instanceof Liquid) {
				//TODO: waterlogging check and do not trigger with powder snow
				return true;
			}
		}

		return false;
	}

	protected function trimPath() : void{
		//TODO: activate this when PM5 is out

		/*if ($this->path !== null) {
			for ($i = 0; $i < $this->path->getNodeCount(); $i++) {
				$node = $this->path->getNode($i);
				if ($this->world->getBlock($node->asVector3()) instanceof FillableCauldron) {
					$this->path->replaceNode($i, $node->cloneAndMove($node->x, $node->y + 1, $node->z));

					$nextNode = $i + 1 < $this->path->getNodeCount() ? $this->path->getNode($i + 1) : null;
					if ($nextNode !== null && $node->y >= $nextNode->y) {
						$this->path->replaceNode($i + 1, $node->cloneAndMove($nextNode->x, $node->y + 1, $nextNode->z));
					}
				}
			}
		}*/
	}

	protected function canMoveDirectly(Vector3 $from, Vector3 $to) : bool{
		return false;
	}

	protected function canCutCorner(BlockPathTypes $pathType) : bool{
		return $pathType !== BlockPathTypes::DANGER_FIRE() &&
			$pathType !== BlockPathTypes::DANGER_OTHER() &&
			$pathType !== BlockPathTypes::WALKABLE_DOOR();
	}

	protected static function isClearForMovementBetween(Mob $mob, Vector3 $from, Vector3 $to, bool $detectLiquids) : bool{
		$to = $to->add(0, $mob->getSize()->getHeight() / 2, 0);

		foreach (VoxelRayTrace::betweenPoints($form, $to) as $pos) {
			$block = $mob->getWorld()->getBlockAt($pos->x, $pos->y, $pos->z);

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
		return $this->world->getBlock($position->down())->isSolid();
	}

	public function getNodeEvaluator() : NodeEvaluator{
		return $this->nodeEvaluator;
	}

	public function canFloat() : bool{
		return $this->nodeEvaluator->canFloat();
	}

	public function setCanFloat(bool $value = true) : void{
		$this->nodeEvaluator->setCanFloat($value);
	}

	public function shouldRecomputePath(Vector3 $updatedBlock) : bool{
		if ($this->hasDelayedRecomputation &&
			$this->path !== null &&
			!$this->path->isDone() &&
			$this->path->getNodeCount() !== 0
		) {
			$endNode = $this->path->getEndNode();
			$targetPos = $endNode->asVector3()->addVector($this->mob->getPosition())->divide(2);

			$updatedCenter = $updatedBlock->add(0.5, 0.5, 0.5);

			return $updatedCenter->distanceSquared($targetPos) < ($this->path->getNodeCount() - $this->path->getNextNodeIndex()) ** 2;
		}

		return false;
	}

	public function getMaxDistanceToWaypoint() : float{
		return $this->maxDistanceToWaypoint;
	}

	public function isStuck() : bool{
		return $this->isStuck;
	}
}
