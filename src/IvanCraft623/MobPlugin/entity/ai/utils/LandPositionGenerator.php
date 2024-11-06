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

use Closure;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\Pathfinder\world\SyncBlockGetter;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

use pocketmine\world\Position;
use const M_PI_2;

/**
 * A utility class for generating random positions on land for entities.
 */
class LandPositionGenerator {

	/**
	 * @param PathfinderMob $entity          The entity to generate the random position for.
	 * @param int           $xzRadius        The maximum horizontal distance from the entity's current position to the random position.
	 * @param int           $yRadius         The maximum vertical distance from the entity's current position to the random position.
	 * @param ?Closure      $targetValueFunc The function used to calculate the desirability of each position.
	 *
	 * @phpstan-param null|Closure(Vector3) : float $targetValueFunc
	 *
	 * Returns a random position within the specified horizontal and vertical range for the given entity, using the
	 * specified function to calculate the desirability of each position.
	 */
	public static function getPos(PathfinderMob $entity, int $xzRadius, int $yRadius, ?Closure $targetValueFunc = null) : ?Vector3{
		$isRestricted = PositionGenerator::isRestricted($entity, $xzRadius);

		return PositionGenerator::generateRandomPos(static function() use ($entity, $xzRadius, $yRadius, $isRestricted) : ?Vector3{
			$position = static::generateRandomPosTowardDirection(
				$entity,
				$xzRadius,
				$isRestricted,
				PositionGenerator::generateRandomDirection($entity->getRandom(), $xzRadius, $yRadius)
			);

			return $position === null ? null : static::movePosUpOutOfSolid($entity, $position);
		}, $targetValueFunc ?? Closure::fromCallable([$entity, 'getWalkTargetValue']));
	}

	/**
	 * @param PathfinderMob $entity     The entity to generate the random position for.
	 * @param int           $directionX the x component of the direction towards which the position is generated
	 * @param int           $directionZ the z component of the direction towards which the position is generated
	 * @param Vector3       $targetPos  The position to move towards.
	 *
	 * Returns a random position within the specified horizontal and vertical range for the given entity, in the
	 * direction of the specified target position.
	 */
	public static function getPosTowards(PathfinderMob $entity, int $directionX, int $directionZ, Vector3 $targetPos) : ?Vector3{
		return self::getPosInDirection($entity, $directionX, $directionZ,
			$targetPos->subtractVector($entity->getPosition()),
			PositionGenerator::isRestricted($entity, $directionX)
		);
	}

	/**
	 * @param PathfinderMob $entity     The entity to generate the random position for.
	 * @param int           $directionX the x component of the direction towards which the position is generated
	 * @param int           $directionZ the z component of the direction towards which the position is generated
	 * @param Vector3       $awayFrom   The position to move away from.
	 *
	 * Returns a random position within the specified horizontal and vertical range for the given entity, away from
	 * the specified position.
	 */
	public static function getPosAway(PathfinderMob $entity, int $directionX, int $directionZ, Vector3 $awayFrom) : ?Vector3{
		return self::getPosInDirection($entity, $directionX, $directionZ,
			$entity->getPosition()->subtractVector($awayFrom),
			PositionGenerator::isRestricted($entity, $directionX)
		);
	}

	/**
	 * @param PathfinderMob $entity          The entity for which to generate the position.
	 * @param int           $xDirection      The x component of the direction.
	 * @param int           $zDirection      The z component of the direction.
	 * @param Vector3       $directionVector The direction vector towards which to generate the position.
	 * @param bool          $isRestricted    Whether the entity is restricted in its movement.
	 *
	 * Returns random position towards the given direction, or null if none could be generated.
	 */
	private static function getPosInDirection(PathfinderMob $entity, int $xDirection, int $zDirection, Vector3 $directionVector, bool $isRestricted) : ?Vector3{
		return PositionGenerator::generateRandomPosForEntity($entity, static function() use ($entity, $xDirection, $zDirection, $directionVector, $isRestricted) : ?Vector3{
			$direction = PositionGenerator::generateRandomDirectionWithinRadians($entity->getRandom(), $xDirection, $zDirection, 0, $directionVector->x, $directionVector->z, M_PI_2);
			if ($direction === null) {
				return null;
			}

			$pos = static::generateRandomPosTowardDirection($entity, $xDirection, $isRestricted, $direction);
			return $pos === null ? null : static::movePosUpOutOfSolid($entity, $pos);
		});
	}

	/**
	 * Moves the given position upwards until it is not inside a solid block and returns it.
	 *
	 * @param PathfinderMob $entity The entity to which the position belongs.
	 * @param Vector3       $pos    The position to move upwards.
	 *
	 * Returns a new position, moved upwards, or null if it could not be moved.
	 */
	public static function movePosUpOutOfSolid(PathfinderMob $entity, Vector3 $pos) : ?Vector3 {
		$world = $entity->getWorld();
		$pos = PositionGenerator::moveUpOutOfSolid($pos, $entity->getWorld()->getMaxY(),
			static fn($position) : bool => $world->getBlock($position)->isSolid()
		);

		return (
			!$world->getBlock($pos) instanceof Water &&
			$entity->getPathfindingMalus(WalkNodeEvaluator::getBlockPathTypeStatic(new SyncBlockGetter($world), (int) $pos->x, (int) $pos->y, (int) $pos->z)) === 0.0
		) ? $pos : null;
	}

	/**
	 * @param PathfinderMob $entity       The entity for which to generate the position.
	 * @param int           $xzRadius     The maximum distance from the entity in the xz-plane.
	 * @param bool          $isRestricted Whether the entity is restricted in its movement.
	 * @param Vector3       $direction    The direction towards which to generate the position.
	 *
	 * Returns random position towards the given direction, or null if none could be generated.
	 */
	public static function generateRandomPosTowardDirection(PathfinderMob $entity, int $xzRadius, bool $isRestricted, Vector3 $direction) : ?Vector3{
		$pos = PositionGenerator::generateRandomPosTowardDirection($entity, $xzRadius, $entity->getRandom(), $direction);

		return ($entity->getWorld()->isInWorld((int) $pos->x, (int) $pos->y, (int) $pos->z) &&
			!($isRestricted && $entity->isWithinRestriction($pos)) &&
			$entity->getNavigation()->isStableDestination($pos)
		) ? $pos : null;
	}
}
