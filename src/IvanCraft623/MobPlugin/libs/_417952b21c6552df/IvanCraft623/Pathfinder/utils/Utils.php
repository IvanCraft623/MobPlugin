<?php

/*
 *  _____      _   _      __ _           _
 * |  __ \    | | | |    / _(_)         | |
 * | |__) |_ _| |_| |__ | |_ _ _ __   __| | ___ _ __
 * |  ___/ _` | __| '_ \|  _| | '_ \ / _` |/ _ \ '__|
 * | |  | (_| | |_| | | | | | | | | | (_| |  __/ |
 * |_|   \__,_|\__|_| |_|_| |_|_| |_|\__,_|\___|_|
 *
 * A PocketMine-MP virion that implements a mob-oriented pathfinding.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\utils;

use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\PathComputationType;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Door;
use pocketmine\block\Slab;
use pocketmine\block\Water;

class Utils {

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

		switch ($block->getTypeId()) {
			case BlockTypeIds::ANVIL:
			case BlockTypeIds::BREWING_STAND:
			case BlockTypeIds::DRAGON_EGG:
			case BlockTypeIds::RESPAWN_ANCHOR:
			case BlockTypeIds::END_ROD:
			case BlockTypeIds::LIGHTNING_ROD:
			//TODO: piston arm
				return false;

			case BlockTypeIds::DEAD_BUSH:
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
}