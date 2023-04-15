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

namespace IvanCraft623\MobPlugin\utils;

use IvanCraft623\MobPlugin\pathfinder\PathComputationType;

use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Door;
use pocketmine\block\Slab;
use pocketmine\block\Water;
use pocketmine\item\Bow;
use pocketmine\item\Releasable;
use function fmod;
use function max;
use function method_exists;
use function min;

class Utils {

	public static function clamp(float $value, float $minValue, float $maxValue) : float {
		return max($minValue, min($maxValue, $value));
	}

	public static function wrapDegrees(float $degrees) : float {
		$result = fmod($degrees, 360);
		if ($result >= 180) {
			$result -= 360;
		}
		if ($result < -180) {
			$result += 360;
		}
		return $result;
	}

	public static function degreesDifference(float $degrees1, float $degrees2) : float {
		return self::wrapDegrees($degrees2 - $degrees1);
	}

	public static function getDefaultProjectileRange(Releasable $item) : int {
		if ($item instanceof Bow) {
			return 15;
		}
		return 8;
	}

	public static function rotateIfNecessary(float $currentDegrees, float $targetDegrees, float $maxDifference) : float {
		return $targetDegrees - self::clamp(self::degreesDifference($currentDegrees, $targetDegrees), -$maxDifference, $maxDifference);
	}

	public static function isPathfindable(Block $block, PathComputationType $pathType) : bool{
		if ($block instanceof Door) {
			if ($pathType->equals(PathComputationType::LAND()) || $pathType->equals(PathComputationType::AIR())) {
				return $block->isOpen();
			}
			return false;
		} elseif ($block instanceof Slab) {
			//TODO: Waterlogging check
			return false;
		}

		switch ($block->getId()) {
			case BlockLegacyIds::ANVIL:
			case BlockLegacyIds::BREWING_STAND_BLOCK:
			case BlockLegacyIds::BREWING_STAND_BLOCK:
			case BlockLegacyIds::DRAGON_EGG:
			//TODO: respawn anchor
			case BlockLegacyIds::END_ROD:
			//TODO: lightning rod
			case BlockLegacyIds::PISTON_ARM_COLLISION:
				return false;

			case BlockLegacyIds::DEAD_BUSH:
				return $pathType->equals(PathComputationType::AIR()) ? true : self::getDefaultPathfindable($block, $pathType);

			default:
				return self::getDefaultPathfindable($block, $pathType);

		}
	}

	private static function getDefaultPathfindable(Block $block, PathComputationType $pathType) : bool{
		return match(true){
			$pathType->equals(PathComputationType::LAND()) => !$block->isFullCube(),
			$pathType->equals(PathComputationType::WATER()) => $block instanceof Water, //TODO: watterlogging check
			$pathType->equals(PathComputationType::AIR()) => !$block->isFullCube(),
			default => false
		};
	}

	public static function arrayContains(object $needle, array $array) : bool{
		$useEquals = method_exists($needle, "equals");
		foreach ($array as $value) {
			if (!$value instanceof $needle) {
				continue;
			}
			if ($useEquals) {
				if ($needle->equals($value)) {
					return true;
				}
			} elseif ($needle === $value) {
				return true;
			}
		}

		return false;
	}
}
