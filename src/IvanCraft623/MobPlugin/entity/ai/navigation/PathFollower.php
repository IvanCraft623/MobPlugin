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
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\libs\_d231130ad4236385\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_d231130ad4236385\IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\math\Vector3;
use function abs;

/**
 * Walks a mob along its path and notices when it gets nowhere.
 *
 * @internal
 */
final class PathFollower{
	/** Move control target for node $wantedNodeIndex of $wantedPath */
	private ?Vector3 $wantedPosition = null;
	private ?Path $wantedPath = null;
	private int $wantedNodeIndex = -1;

	private StuckDetector $stuckDetector;

	public function __construct(private Mob $mob){
		$this->stuckDetector = new StuckDetector();
	}

	/**
	 * Advances $path when the mob reaches its next node or can cut the corner.
	 */
	public function follow(Path $path, Locomotion $locomotion, TraversalRules $rules, int $tick) : StuckResult{
		if ($path->isDone()) {
			return StuckResult::NONE;
		}

		$position = $locomotion->pathPosition($this->mob, $rules);
		$width = $this->mob->getSize()->getWidth();
		$maxDistanceToWaypoint = $width > 0.75 ? $width / 2 : 0.75 - $width / 2;
		$nodePos = $path->getNextNodePos();
		$mobPos = $this->mob->getPosition();

		$dx = abs($mobPos->getX() - ($nodePos->getX() + 0.5));
		$dy = abs($mobPos->getY() - $nodePos->getY());
		$dz = abs($mobPos->getZ() - ($nodePos->getZ() + 0.5));

		if (($dx < $maxDistanceToWaypoint && $dz < $maxDistanceToWaypoint && $dy < 1) ||
			($locomotion->canCutCorner($path->getNextNode()->type) && $this->shouldTargetNextNodeInDirection($path, $locomotion, $position))
		) {
			$path->advance();
		}

		return $this->stuckDetector->check(
			$tick,
			$position,
			$this->mob->getMotionSpeed(),
			$path->isDone() ? null : $path->getNextNodePos(),
			$this->mob->getWorld()->getTime()
		);
	}

	public function getWantedPosition(Path $path, Locomotion $locomotion, BlockGetter $blockGetter) : Vector3{
		$nodeIndex = $path->getNextNodeIndex();
		if ($this->wantedPosition === null || $path !== $this->wantedPath || $nodeIndex !== $this->wantedNodeIndex) {
			$nextPos = $path->getNextNodeCenter();
			$this->wantedPosition = new Vector3($nextPos->x, $locomotion->wantedY($this->mob, $blockGetter, $nextPos), $nextPos->z);
			$this->wantedPath = $path;
			$this->wantedNodeIndex = $nodeIndex;
		}
		return $this->wantedPosition;
	}

	/**
	 * The ground under the node being walked to may not be where it was.
	 */
	public function forgetWantedPosition() : void{
		$this->wantedPosition = null;
		$this->wantedPath = null;
	}

	public function restartStuckCheck(int $tick, Vector3 $position) : void{
		$this->stuckDetector->restart($tick, $position);
	}

	public function resetNodeProgress() : void{
		$this->stuckDetector->resetNode();
	}

	private function shouldTargetNextNodeInDirection(Path $path, Locomotion $locomotion, Vector3 $direction) : bool{
		if ($path->getNextNodeIndex() + 1 >= $path->getNodeCount()) {
			return false;
		}

		$nodePos = $path->getNextNodePos()->add(0.5, 0, 0.5);

		if ($direction->distanceSquared($nodePos) > 4) {
			return false;
		}
		if ($locomotion->canMoveDirectly($this->mob, $direction, $path->getNextNodeCenter())) {
			return true;
		}

		$nextNodePos = $path->getNodePos($path->getNextNodeIndex() + 1)->add(0.5, 0, 0.5);

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