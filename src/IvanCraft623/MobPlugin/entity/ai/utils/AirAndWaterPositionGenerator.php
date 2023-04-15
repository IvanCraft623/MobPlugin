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
use IvanCraft623\MobPlugin\pathfinder\evaluator\WalkNodeEvaluator;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\Position;

/**
 * A utility class for generating random positions in air and water for entities.
 */
class AirAndWaterPositionGenerator {

	/**
	 * @param PathfinderMob $entity          the entity for which the position is generated
	 * @param int           $horizontalRange the maximum horizontal distance from the entity
	 * @param int           $verticalRange   the maximum vertical distance from the entity
	 * @param int           $yCenter         the center y coordinate for the position
	 * @param float         $directionX      the x component of the direction towards which the position is generated
	 * @param float         $directionZ      the z component of the direction towards which the position is generated
	 * @param float         $maxAngle        the maximum angle, in radians, between the generated position and the direction
	 *
	 * Returns a random position in air or water towards the specified direction, or null if no position could be generated
	 */
	public static function getPos(PathfinderMob $entity, int $horizontalRange, int $verticalRange, int $yCenter, float $directionX, float $directionZ, float $maxAngle) : ?Vector3{
		$isRestricted = PositionGenerator::isRestricted($entity, $horizontalRange);
		return PositionGenerator::generateRandomPosForEntity($entity,
			fn() : ?Vector3 => static::generateRandomPos($entity, $horizontalRange, $verticalRange, $yCenter, $directionX, $directionZ, $maxAngle, $isRestricted)
		);
	}

	/**
	 * @param PathfinderMob $entity          the entity for which the position is generated
	 * @param int           $horizontalRange the maximum horizontal distance from the entity
	 * @param int           $verticalRange   the maximum vertical distance from the entity
	 * @param int           $yCenter         the center y coordinate for the position
	 * @param float         $directionX      the x component of the direction towards which the position is generated
	 * @param float         $directionZ      the z component of the direction towards which the position is generated
	 * @param float         $maxAngle        the maximum angle, in radians, between the generated position and the direction
	 * @param bool          $isRestricted    whether the entity is restricted from moving to certain positions
	 *
	 * Returns a random position air or water towards the specified direction, or null if no position could be generated
	 */
	public static function generateRandomPos(PathfinderMob $entity, int $horizontalRange, int $verticalRange, int $yCenter, float $directionX, float $directionZ, float $maxAngle, bool $isRestricted) : ?Vector3{
		$direction = PositionGenerator::generateRandomDirectionWithinRadians($entity->getRandom(), $horizontalRange, $verticalRange, $yCenter, $directionX, $directionZ, $maxAngle);
		if ($direction === null) {
			return null;
		}

		$pos = PositionGenerator::generateRandomPosTowardDirection($entity, $horizontalRange, $entity->getRandom(), $direction);
		$world = $entity->getWorld();

		if ($world->isInWorld((int) $pos->x, (int) $pos->y, (int) $pos->z) &&
			!($isRestricted && $entity->isWithinRestriction($pos))
		) {
			$pos = PositionGenerator::moveUpOutOfSolid($pos, $world->getMaxY(),
				static fn($position) : bool => $world->getBlock($position)->isSolid()
			);

			return ($entity->getPathfindingMalus(WalkNodeEvaluator::getBlockPathTypeStatic($world, (int) $pos->x, (int) $pos->y, (int) $pos->z)) === 0.0
			) ? $pos : null;
		}

		return null;
	}
}
