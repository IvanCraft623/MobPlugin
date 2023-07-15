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

use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\Position;
use function abs;
use function atan2;
use function cos;
use function floor;
use function intdiv;
use function sin;
use function sqrt;
use const INF;
use const M_PI_2;
use const M_SQRT2;

/**
 * A utility class for generating random positions for entities.
 */
class PositionGenerator {

	/**
	 * The number of attempts to generate a random position.
	 */
	public const RANDOM_POS_ATTEMPTS = 10;

	/**
	 * Generates a random Vector3 within a radius around (0,0,0).
	 *
	 * @param Random $random   the random number generator.
	 * @param int    $xzRadius the radius in the horizontal plane.
	 * @param int    $yRadius  the radius in the vertical plane.
	 *
	 * Returns a random Vector3 within the specified radius.
	 */
	public static function generateRandomDirection(Random $random, int $xzRadius, int $yRadius) : Vector3{
		return new Vector3(
			$random->nextBoundedInt(2 * $xzRadius + 1) - $xzRadius,
			$random->nextBoundedInt(2 * $yRadius + 1) - $yRadius,
			$random->nextBoundedInt(2 * $xzRadius + 1) - $xzRadius
		);
	}

	/**
	 * Generates a random Vector3 within a certain spread around a center point,
	 * with a certain angle and height spread.
	 *
	 * @param Random $random      the random number generator.
	 * @param int    $xzRadius    the radius in the horizontal plane.
	 * @param int    $yRadius     the radius in the vertical plane.
	 * @param int    $yCenter     the center Y coordinate.
	 * @param float  $directionX  the x component of the direction.
	 * @param float  $directionZ  the z component of the direction.
	 * @param float  $angleSpread the angle spread.
	 *
	 * Returns a random BlockPos within the specified spread, or null if it cannot be generated.
	 */
	public static function generateRandomDirectionWithinRadians(Random $random, int $xzRadius, int $yRadius, int $yCenter, float $directionX, float $directionZ, float $angleSpread) : ?Vector3{
		$angle = atan2($directionZ, $directionX) - M_PI_2;
		$angleOffset = $angle + (2 * $random->nextFloat() - 1) * $angleSpread;
		$distance = sqrt($random->nextFloat()) * M_SQRT2 * $xzRadius;

		$x = -$distance * sin($angleOffset);
		$z = $distance * cos($angleOffset);

		if (abs($x) < $xzRadius && abs($z) < $xzRadius) {
			return new Vector3(
				floor($x),
				floor($random->nextBoundedInt(2 * $yRadius + 1) - $yRadius + $yCenter),
				floor($z)
			);
		}

		return null;
	}

	/**
	 * Moves a given Vector3 up until it is no longer solid or until it reaches a maximum vertical distance.
	 *
	 * @param Vector3  $pos                 the starting Vector3.
	 * @param int      $maxVerticalDistance the maximum vertical distance to move.
	 * @param \Closure $isSolid             a closure that checks if a Vector3 is solid.
	 *
	 * @phpstan-param \Closure(Vector3) : bool $isSolid
	 *
	 * Returns the first Vector3 above the starting Vector3 that is not solid, or the starting Vector3 if it is not solid.
	 */
	public static function moveUpOutOfSolid(Vector3 $pos, int $maxVerticalDistance, \Closure $isSolid) : Vector3{
		if (!$isSolid($pos)) {
			return $pos;
		}
		$newPos = $pos->up();

		while ($newPos->getY() < $maxVerticalDistance && $isSolid($newPos)) {
			$newPos = $newPos->up();
		}

		return $newPos;
	}

	/**
	 * Moves a given Vector3 up to a certain amount of blocks above the first Vector3 above it that is not solid.
	 *
	 * @param Vector3  $pos                 the starting Vector3.
	 * @param int      $maxVerticalDistance the maximum vertical distance to move.
	 * @param int      $aboveSolidAmount    the maximum amount of blocks to move above the first Vector3 above the starting Vector3 that is not solid.
	 * @param \Closure $isSolid             a closure that checks if a Vector3 is solid.
	 *
	 * @phpstan-param \Closure(Vector3) : bool $isSolid
	 *
	 * Returns the first Vector3 above the starting Vector3 that is not solid, or the starting Vector3 if it is not solid.
	 * If a solid block is encountered while moving up, returns the last Vector3 that was not solid.
	 */
	public static function moveUpToAboveSolid(Vector3 $pos, int $maxVerticalDistance, int $aboveSolidAmount, \Closure $isSolid) : Vector3{
		if ($aboveSolidAmount < 0) {
			throw new \InvalidArgumentException("Above solid amount must be at least of 0");
		}

		if ($isSolid($pos)) {
			return $pos;
		}

		$newPos = $pos->up();
		while ($newPos->y < $maxVerticalDistance && $isSolid($newPos)) {
			$newPos = $newPos->up();
		}

		$aboveSolidPos = $newPos;
		for (;
			$aboveSolidPos->y < $maxVerticalDistance && $aboveSolidPos->y - $newPos->y < $aboveSolidAmount;
			$aboveSolidPos = $nextPos
		) {
			$nextPos = $aboveSolidPos->up();
			if ($isSolid($nextPos)) {
				break;
			}
		}

		return $aboveSolidPos;
	}

	/**
	 * Generates a random Vector3 position for a given entity.
	 *
	 * @param PathfinderMob $entity      the entity for which to generate a random position.
	 * @param \Closure      $posSupplier a supplier for random BlockPos values.
	 *
	 * @phpstan-param \Closure() : ?Vector3 $posSupplier
	 *
	 * Returns a random Vector3 position generated from a BlockPos, or null if none could be found.
	 */
	public static function generateRandomPosForEntity(PathfinderMob $entity, \Closure $posSupplier) : ?Vector3{
		return static::generateRandomPos($posSupplier, \Closure::fromCallable([$entity, 'getWalkTargetValue']));
	}

	/**
	 * This function attempts to generate a random position and returns the position with
	 * the highest target value, as determined by the target value function.
	 *
	 * @param \Closure $posSupplier     a supplier function that returns a random block position.
	 * @param \Closure $targetValueFunc a function that calculates the target value of a block position.
	 *
	 * @phpstan-param \Closure() : ?Vector3 $posSupplier
	 * @phpstan-param \Closure(Vector3) : float $targetValueFunc
	 *
	 * Returns a Vector3 object representing the highest valued block position, or null if no
	 * suitable position could be found.
	 */
	public static function generateRandomPos(\Closure $posSupplier, \Closure $targetValueFunc) : ?Vector3{
		$bestValue = -INF;
		$bestPos = null;

		for ($i = 0; $i < self::RANDOM_POS_ATTEMPTS; ++$i) {
			$pos = $posSupplier();
			if ($pos === null) {
				continue;
			}

			$value = $targetValueFunc($pos);
			if ($value > $bestValue) {
				$bestValue = $value;
				$bestPos = $pos;
			}
		}

		return $bestPos !== null ? $bestPos->floor()->add(0.5, 0, 0.5) : null;
	}

	/**
	 * Generates a random Vector3 towards a target position, with a specified radius.
	 *
	 * @param PathfinderMob $entity    entity that will be moving towards the target position.
	 * @param int           $radius    maximum radius that the Vector3 can be generated from the target position.
	 * @param Random        $random    source of randomness used for generating the new Vector3.
	 * @param Vector3       $targetPos target Vector3 that the new Vector3 will be generated towards.
	 *
	 * Returns a random generated Vector3.
	 */
	public static function generateRandomPosTowardDirection(PathfinderMob $entity, int $radius, Random $random, Vector3 $targetPos) : Vector3{
		$targetX = $targetPos->x;
		$targetZ = $targetPos->z;

		$entityPos = $entity->getPosition();

		if ($entity->hasRestriction() && $radius > 1) {
			$restrictionCenter = $entity->getRestrictCenter();
			$halfRadius = intdiv($radius, 2);

			if ($entityPos->x > $restrictionCenter->x) {
				$targetX -= $random->nextBoundedInt($halfRadius);
			} else {
				$targetX += $random->nextBoundedInt($halfRadius);
			}

			if ($entityPos->z > $restrictionCenter->z) {
				$targetZ -= $random->nextBoundedInt($halfRadius);
			} else {
				$targetZ += $random->nextBoundedInt($halfRadius);
			}
		}

		return new Vector3(
			floor($targetX + $entityPos->x),
			floor($targetPos->y + $entityPos->y),
			floor($targetZ + $entityPos->z)
		);
	}

	public static function isRestricted(PathfinderMob $entity, int $xzRadius) : bool{
		return $entity->hasRestriction() &&
			$entity->getRestrictCenter()->floor()->add(0.5, 0.5, 0.5)
				->distanceSquared($entity->getPosition()) < ($entity->getRestrictRadius() + $xzRadius + 1) ** 2;
	}
}
