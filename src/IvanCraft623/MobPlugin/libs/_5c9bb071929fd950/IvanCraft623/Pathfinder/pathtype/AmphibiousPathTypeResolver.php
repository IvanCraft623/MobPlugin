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

namespace IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\pathtype;

use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\math\Facing;

class AmphibiousPathTypeResolver extends WalkPathTypeResolver{
	public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		if (BlockClassifier::getRawType($blockGetter, $x, $y, $z) !== BlockPathType::WATER) {
			return parent::getBlockPathType($blockGetter, $x, $y, $z);
		}

		foreach (Facing::OFFSET as [$dx, $dy, $dz]) {
			if (BlockClassifier::getRawType($blockGetter, $x + $dx, $y + $dy, $z + $dz) === BlockPathType::BLOCKED) {
				return BlockPathType::WATER_BORDER;
			}
		}

		return BlockPathType::WATER;
	}
}