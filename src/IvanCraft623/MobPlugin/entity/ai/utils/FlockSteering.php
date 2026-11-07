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

namespace IvanCraft623\MobPlugin\entity\ai\utils;

use IvanCraft623\MobPlugin\entity\Flocking;
use IvanCraft623\MobPlugin\entity\FlockingParameters;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;
use function count;
use function floor;
use function max;
use function min;

/**
 * Works out the heading and speed of each follower of a flock.
 */
final class FlockSteering{

	/** Speed modifier of a follower far behind */
	private const MAX_HURRY = 2.5;
	/** Speed modifier of a follower with the leader still */
	private const MIN_SPEED = 0.3;
	/** Blocks per tick a follower gains on the leader for each block of gap */
	private const CATCH_UP = 0.05;
	/** Speed modifier of a follower that got ahead of the leader */
	private const WAIT_SPEED = 0.4;
	/** Blocks per tick from which the leader counts as moving */
	private const MOVING_SPEED = 0.02;

	private function __construct(){}

	/**
	 * @phpstan-param PathfinderMob&Flocking          $leader
	 * @phpstan-param (PathfinderMob&Flocking)[]      $followers
	 */
	public static function steer(PathfinderMob $leader, array $followers, Vector3 $groupVelocity) : void{
		$parameters = $leader->getFlockingParameters();
		$leaderPosition = $leader->getLocation();
		$positions = [$leaderPosition];
		foreach ($followers as $follower) {
			$positions[] = $follower->getLocation();
		}

		$center = $parameters->useCenterOfMass ? self::average($positions) : $leaderPosition;
		$navigation = $leader->getNavigation();
		$goal = $navigation->isDone() ? null : $navigation->getWantedPosition();

		foreach ($followers as $index => $follower) {
			$position = $positions[$index + 1];
			$toCenter = $center->subtractVector($position);
			$centerDistance = $toCenter->length();
			$leaderDistance = $position->distance($leaderPosition);

			$heading = $groupVelocity->subtractVector($follower->getMotion())
				->addVector(self::separation($positions, $index + 1, $parameters)->multiply($parameters->separationWeight))
				->addVector(self::cohesion($toCenter, $centerDistance, $parameters)->multiply($parameters->cohesionWeight))
				->addVector(self::goalPull($goal, $position)->multiply($parameters->goalWeight));

			$follower->getFlockMembership()->steer(
				self::slideAlongWall($follower, $position, self::keepBelowSurface($follower, $position, $heading, $parameters), $parameters)->normalize(),
				self::speedModifier($position, $leaderPosition, $groupVelocity, $parameters)
			);
		}
	}

	/**
	 * @param Vector3[] $positions
	 */
	private static function average(array $positions) : Vector3{
		$count = count($positions);
		$x = $y = $z = 0.0;
		foreach ($positions as $position) {
			$x += $position->x;
			$y += $position->y;
			$z += $position->z;
		}

		return new Vector3($x / $count, $y / $count, $z / $count);
	}

	/**
	 * @param Vector3[] $positions
	 */
	private static function separation(array $positions, int $self, FlockingParameters $parameters) : Vector3{
		$away = new Vector3(0, 0, 0);
		foreach ($positions as $index => $other) {
			if ($index !== $self && $positions[$self]->distanceSquared($other) < $parameters->separationThreshold ** 2) {
				$away = $away->addVector($positions[$self]->subtractVector($other));
			}
		}

		return $away->normalize();
	}

	private static function cohesion(Vector3 $toCenter, float $centerDistance, FlockingParameters $parameters) : Vector3{
		return $centerDistance > $parameters->innerCohesionThreshold ? $toCenter->normalize() : new Vector3(0, 0, 0);
	}

	private static function goalPull(?Vector3 $goal, Vector3 $position) : Vector3{
		return $goal === null ? new Vector3(0, 0, 0) : $goal->subtractVector($position)->normalize();
	}

	/**
	 * @phpstan-param PathfinderMob&Flocking $mob
	 */
	private static function keepBelowSurface(PathfinderMob $mob, Vector3 $position, Vector3 $heading, FlockingParameters $parameters) : Vector3{
		if ($parameters->staysBelowSurface && $heading->y > 0 &&
			!$mob->canFlockThrough($mob->getWorld()->getBlockAt((int) floor($position->x), (int) floor($position->y) + 1, (int) floor($position->z)))
		) {
			return new Vector3($heading->x, 0, $heading->z);
		}

		return $heading;
	}

	/**
	 * Slides the heading along a block ahead that can't be moved through, on the mob's own level only.
	 *
	 * @phpstan-param PathfinderMob&Flocking $mob
	 */
	private static function slideAlongWall(PathfinderMob $mob, Vector3 $position, Vector3 $heading, FlockingParameters $parameters) : Vector3{
		$ahead = (new Vector3($heading->x, 0, $heading->z))->normalize();
		if ($parameters->blockWeight === 0.0 || $ahead->lengthSquared() === 0.0) {
			return $heading;
		}

		$look = $position->addVector($ahead->multiply($parameters->blockDistance));
		$block = $mob->getWorld()->getBlockAt((int) floor($look->x), (int) floor($position->y), (int) floor($look->z));
		if ($mob->canFlockThrough($block)) {
			return $heading;
		}

		$blockPosition = $block->getPosition();
		$away = (new Vector3($position->x - ($blockPosition->x + 0.5), 0, $position->z - ($blockPosition->z + 0.5)))->normalize();
		$into = $heading->dot($away);
		if ($into < 0) {
			$heading = $heading->subtractVector($away->multiply($into));
		}

		return $heading->addVector($away->multiply($parameters->blockWeight));
	}

	/**
	 * The leader's pace plus a catch-up for the gap, as a multiple of the mob's own speed. Slows down when well ahead.
	 */
	private static function speedModifier(Vector3 $position, Vector3 $leaderPosition, Vector3 $leaderVelocity, FlockingParameters $parameters) : float{
		$toLeader = $leaderPosition->subtractVector($position);
		$leaderSpeed = $leaderVelocity->length();
		if ($leaderSpeed >= self::MOVING_SPEED && $toLeader->dot($leaderVelocity->divide($leaderSpeed)) < -$parameters->innerCohesionThreshold) {
			return self::WAIT_SPEED;
		}

		$gap = max(0.0, $toLeader->length() - $parameters->innerCohesionThreshold);

		return min(self::MAX_HURRY, max(self::MIN_SPEED, ($leaderSpeed + (self::CATCH_UP * $gap)) / $parameters->speed));
	}
}
