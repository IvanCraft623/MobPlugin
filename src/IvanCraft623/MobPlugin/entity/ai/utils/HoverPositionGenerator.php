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
use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\Pathfinder\world\SyncBlockGetter;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\Position;

/**
 * A utility class for generating random positions for entitities to hover towards.
 */
class HoverPositionGenerator {

	/**
	 * @param PathfinderMob $entity     The entity for which to generate the position.
	 * @param int           $xzRadius   The maximum distance from the entity in the horizontal plane.
	 * @param int           $yRadius    The maximum distance from the entity in the vertical axis.
	 * @param float         $directionX The x component of the direction towards which the position is generated.
	 * @param float         $directionZ The z component of the direction towards which the position is generated.
	 * @param float         $angle      The angle spread, in radians.
	 * @param int           $minHeight  The minimum height above the ground for the generated position.
	 * @param int           $maxHeight  The maximum height above the ground for the generated position.
	 *
	 * Returns a random position towards a given direction, or null if none could be generated.
	 */
	public static function getPos(PathfinderMob $entity, int $xzRadius, int $yRadius, float $directionX, float $directionZ, float $angle, int $minHeight, int $maxHeight) : ?Vector3{
		$isRestricted = PositionGenerator::isRestricted($entity, $xzRadius);

		return PositionGenerator::generateRandomPosForEntity($entity, static function() use ($entity, $xzRadius, $yRadius, $directionX, $directionZ, $angle, $minHeight, $maxHeight, $isRestricted) : ?Vector3{
			$direction = PositionGenerator::generateRandomDirectionWithinRadians(
				$entity->getRandom(), $xzRadius, $yRadius, 0, $directionX, $directionZ, $angle
			);
			if ($direction === null) {
				return null;
			}

			$pos = LandPositionGenerator::generateRandomPosTowardDirection($entity, $xzRadius, $isRestricted, $direction);
			if ($pos === null) {
				return null;
			}

			$world = $entity->getWorld();
			$pos = PositionGenerator::moveUpToAboveSolid($pos,
				$entity->getRandom()->nextBoundedInt($maxHeight - $minHeight + 1) + $minHeight, $entity->getWorld()->getMaxY(),
				static fn($position) : bool => $world->getBlock($position)->isSolid()
			);
			return (!$world->getBlock($pos) instanceof Water &&
				$entity->getPathfindingMalus(WalkNodeEvaluator::getBlockPathTypeStatic(new SyncBlockGetter($world), (int) $pos->x, (int) $pos->y, (int) $pos->z)) === 0.0
			) ? $pos : null;
		});
	}
}
