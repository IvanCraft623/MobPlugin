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
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\Position;

/**
 * A utility class for generating random positions in air for entities.
 */
class AirPositionGenerator {

	/**
	 * Generates a random position in the air towards a given target, within the specified horizontal and vertical range.
	 *
	 * @param PathfinderMob $entity          the PathfinderMob entity that is moving towards the target
	 * @param int           $horizontalRange the maximum horizontal distance from the entity to the generated position
	 * @param int           $verticalRange   the maximum vertical distance from the entity to the generated position
	 * @param int           $yCenter         the y-coordinate around which to generate the position
	 * @param Vector3       $target          the target position to move towards
	 * @param float         $maxAngle        the maximum angle (in radians) to deviate from the direction to the target
	 *
	 * Returns a random position in the air towards the target, or null if none could be generated
	 */
	public static function getPosTowards(PathfinderMob $entity, int $horizontalRange, int $verticalRange, int $yCenter, Vector3 $target, float $maxAngle) : ?Vector3{
		$diff = $target->subtractVector($entity->getPosition());
		$isRestricted = PositionGenerator::isRestricted($entity, $horizontalRange);

		return PositionGenerator::generateRandomPosForEntity($entity,
			static function() use ($entity, $horizontalRange, $verticalRange, $yCenter, $maxAngle, $diff, $isRestricted) : ?Vector3{
				$pos = AirAndWaterPositionGenerator::generateRandomPos(
					$entity,
					$horizontalRange,
					$verticalRange,
					$yCenter,
					$diff->x,
					$diff->z,
					$maxAngle,
					$isRestricted
				);

				return ($pos !== null && !$entity->getWorld()->getBlock($pos) instanceof Water) ? $pos : null;
			}
		);
	}
}
