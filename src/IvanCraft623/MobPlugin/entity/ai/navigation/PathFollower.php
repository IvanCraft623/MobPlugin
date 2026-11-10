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

use IvanCraft623\MobPlugin\entity\ai\navigation\locomotion\Locomotion;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\PathCursor;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\math\Vector3;
use function abs;

/**
 * The path a mob is following: advances it, gives the next position to reach and notices when the mob gets
 * nowhere.
 *
 * @internal
 */
final class PathFollower{
	private ?PathCursor $cursor = null;

	/** Move control target for node $wantedNodeIndex of $wantedCursor */
	private ?Vector3 $wantedPosition = null;
	private ?PathCursor $wantedCursor = null;
	private int $wantedNodeIndex = -1;

	private StuckDetector $stuckDetector;

	public function __construct(private PathfinderMob $mob){
		$this->stuckDetector = new StuckDetector();
	}

	public function getCursor() : ?PathCursor{
		return $this->cursor;
	}

	/**
	 * Follows $cursor from its first node, or nothing for null.
	 */
	public function setCursor(?PathCursor $cursor) : void{
		$this->cursor = $cursor;
		$this->forgetWantedPosition();
		$this->resetNodeProgress();
	}

	/**
	 * Advances the path when the mob reaches its next node or can cut the corner.
	 */
	public function follow(Locomotion $locomotion, TraversalRules $rules, int $tick) : StuckResult{
		$cursor = $this->cursor;
		if ($cursor === null || $cursor->isDone()) {
			return StuckResult::NONE;
		}

		$position = $locomotion->pathPosition($this->mob, $rules);
		$width = $this->mob->getSize()->getWidth();
		$maxDistanceToWaypoint = $width > 0.75 ? $width / 2 : 0.75 - $width / 2;
		$nodePos = $cursor->getNextNodePos();
		$mobPos = $this->mob->getPosition();

		$dx = abs($mobPos->getX() - ($nodePos->getX() + 0.5));
		$dy = abs($mobPos->getY() - $nodePos->getY());
		$dz = abs($mobPos->getZ() - ($nodePos->getZ() + 0.5));

		if (($dx < $maxDistanceToWaypoint && $dz < $maxDistanceToWaypoint && $dy < 1) ||
			($locomotion->canCutCorner($cursor->getNextNode()->type) && $this->shouldTargetNextNodeInDirection($cursor, $locomotion, $position))
		) {
			$cursor->advance();
		}

		return $this->stuckDetector->check(
			$tick,
			$position,
			$this->mob->getMovementInput()->getSpeed(),
			$cursor->isDone() ? null : $cursor->getNextNodePos(),
			$this->mob->getWorld()->getTime()
		);
	}

	/**
	 * Where the mob goes next, null without a path left to walk.
	 */
	public function getWantedPosition(Locomotion $locomotion, BlockGetter $blockGetter) : ?Vector3{
		$cursor = $this->cursor;
		if ($cursor === null || $cursor->isDone()) {
			return null;
		}
		$nodeIndex = $cursor->getNextNodeIndex();
		if ($this->wantedPosition === null || $cursor !== $this->wantedCursor || $nodeIndex !== $this->wantedNodeIndex) {
			$nextPos = $cursor->getNextNodeCenter();
			$this->wantedPosition = new Vector3($nextPos->x, $locomotion->wantedY($this->mob, $blockGetter, $nextPos), $nextPos->z);
			$this->wantedCursor = $cursor;
			$this->wantedNodeIndex = $nodeIndex;
		}
		return $this->wantedPosition;
	}

	/**
	 * The ground under the node being walked to may not be where it was.
	 */
	public function forgetWantedPosition() : void{
		$this->wantedPosition = null;
		$this->wantedCursor = null;
	}

	public function restartStuckCheck(int $tick, Vector3 $position) : void{
		$this->stuckDetector->restart($tick, $position);
	}

	public function resetNodeProgress() : void{
		$this->stuckDetector->resetNode();
	}

	private function shouldTargetNextNodeInDirection(PathCursor $cursor, Locomotion $locomotion, Vector3 $direction) : bool{
		if ($cursor->getNextNodeIndex() + 1 >= $cursor->getPath()->getNodeCount()) {
			return false;
		}

		$nodePos = $cursor->getNextNodePos()->add(0.5, 0, 0.5);

		if ($direction->distanceSquared($nodePos) > 4) {
			return false;
		}
		if ($locomotion->canMoveDirectly($this->mob, $direction, $cursor->getNextNodeCenter())) {
			return true;
		}

		$nextNodePos = $cursor->getPath()->getNodePos($cursor->getNextNodeIndex() + 1)->add(0.5, 0, 0.5);

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
}