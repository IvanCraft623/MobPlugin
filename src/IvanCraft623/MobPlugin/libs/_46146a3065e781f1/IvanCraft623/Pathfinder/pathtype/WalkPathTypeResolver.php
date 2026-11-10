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

namespace IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\pathtype;

use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\world\BlockGetter;

class WalkPathTypeResolver extends PathTypeResolver{
	public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		return BlockClassifier::getType($blockGetter, $x, $y, $z);
	}
}