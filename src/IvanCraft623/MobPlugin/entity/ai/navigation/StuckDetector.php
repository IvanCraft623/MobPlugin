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

use pocketmine\math\Vector3;
use const INF;

/**
 * Tells when a mob is not getting anywhere along its path.
 *
 * @internal
 */
final class StuckDetector{
	/** Ticks between checks of the distance moved */
	public const DISTANCE_CHECK_INTERVAL = 100;
	public const EXPECTED_DISTANCE_FACTOR = 0.25;
	/** Times the expected time a mob may stay on a node */
	public const TIMEOUT_FACTOR = 3;
	/** Checks without getting closer to a node before the mob counts as blocked */
	public const BLOCKED_CHECKS = 20;
	/** Least progress, in blocks, that counts as getting closer */
	public const PROGRESS_EPSILON = 0.05;

	private int $lastCheckTick = 0;

	private Vector3 $lastCheckPosition;

	private Vector3 $node;

	private int $timeOnNode = 0;

	private int $lastTime = 0;

	private float $timeLimit = 0;

	private float $closestDistance = INF;

	private int $checksWithoutProgress = 0;

	public function __construct(){
		$this->lastCheckPosition = Vector3::zero();
		$this->node = Vector3::zero();
	}

	/**
	 * Measures the distance moved from $position on.
	 */
	public function restart(int $tick, Vector3 $position) : void{
		$this->lastCheckTick = $tick;
		$this->lastCheckPosition = $position;
	}

	/**
	 * Forgets the node being walked to.
	 */
	public function resetNode() : void{
		$this->node = Vector3::zero();
		$this->timeOnNode = 0;
		$this->timeLimit = 0;
		$this->closestDistance = INF;
		$this->checksWithoutProgress = 0;
	}

	/**
	 * @param float        $speed    the mob's movement speed
	 * @param Vector3|null $nextNode block of the node it walks to, if any
	 * @param int          $time     world time, which keeps running while the mob stands still
	 */
	public function check(int $tick, Vector3 $position, float $speed, ?Vector3 $nextNode, int $time) : StuckResult{
		if ($this->isStuck($tick, $position, $speed)) {
			return StuckResult::STUCK;
		}

		return $nextNode === null ? StuckResult::NONE : $this->checkNode($position, $speed, $nextNode, $time);
	}

	/**
	 * Whether the mob moved less than expected since the last check, once an interval has passed.
	 */
	private function isStuck(int $tick, Vector3 $position, float $speed) : bool{
		if ($tick - $this->lastCheckTick <= self::DISTANCE_CHECK_INTERVAL) {
			return false;
		}

		$expectedSpeed = $speed >= 1 ? $speed : $speed ** 2;
		$stuck = $position->distanceSquared($this->lastCheckPosition) < (($expectedSpeed * 100 * self::EXPECTED_DISTANCE_FACTOR) ** 2);

		$this->lastCheckTick = $tick;
		$this->lastCheckPosition = $position;
		return $stuck;
	}

	/**
	 * Whether the mob is getting to the node it is walking to, and in time.
	 */
	private function checkNode(Vector3 $position, float $speed, Vector3 $nextNode, int $time) : StuckResult{
		$distanceToNode = $position->distance($nextNode->add(0.5, 0, 0.5));
		if ($nextNode->equals($this->node)) {
			$this->timeOnNode += $time - $this->lastTime;
			if ($distanceToNode < $this->closestDistance - self::PROGRESS_EPSILON) {
				$this->closestDistance = $distanceToNode;
				$this->checksWithoutProgress = 0;
			} elseif (++$this->checksWithoutProgress > self::BLOCKED_CHECKS) {
				$this->resetNode();
				return StuckResult::BLOCKED;
			}
		} else {
			$this->node = $nextNode;
			$this->timeLimit = $speed > 0 ? $distanceToNode / $speed * 20 : 0;
			$this->closestDistance = $distanceToNode;
			$this->checksWithoutProgress = 0;
		}

		if ($this->timeLimit > 0 && $this->timeOnNode > $this->timeLimit * self::TIMEOUT_FACTOR) {
			$this->resetNode();
			return StuckResult::TIMED_OUT;
		}

		$this->lastTime = $time;
		return StuckResult::NONE;
	}
}
