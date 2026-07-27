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

namespace IvanCraft623\MobPlugin\libs\_6afff87222961196\IvanCraft623\Pathfinder;

use pocketmine\utils\LegacyEnumShimTrait;

enum BlockPathType{
	use LegacyEnumShimTrait;

	public const OPEN_MALUS = 0;
	public const BLOCKED_MALUS = -1;

	case BLOCKED;
	case OPEN;
	case WALKABLE;
	case WALKABLE_DOOR;
	case TRAPDOOR;
	case POWDER_SNOW;
	case DANGER_POWDER_SNOW;
	case FENCE;
	case LAVA;
	case WATER;
	case WATER_BORDER;
	case RAIL;
	case UNPASSABLE_RAIL;
	case DANGER_FIRE;
	case DAMAGE_FIRE;
	case DANGER_OTHER;
	case DAMAGE_OTHER;
	case DOOR_OPEN;
	case DOOR_WOOD_CLOSED;
	case DOOR_IRON_CLOSED;
	case BREACH;
	case LEAVES;
	case STICKY_HONEY;
	case COCOA;

	public function getMalus() : float{
		return match($this){
			self::BLOCKED => self::BLOCKED_MALUS,
			self::OPEN => self::OPEN_MALUS,
			self::WALKABLE => self::OPEN_MALUS,
			self::WALKABLE_DOOR => self::OPEN_MALUS,
			self::TRAPDOOR => self::OPEN_MALUS,
			self::POWDER_SNOW => self::BLOCKED_MALUS,
			self::DANGER_POWDER_SNOW => self::OPEN_MALUS,
			self::FENCE => self::BLOCKED_MALUS,
			self::LAVA => self::BLOCKED_MALUS,
			self::WATER => 8,
			self::WATER_BORDER => 8,
			self::RAIL => self::OPEN_MALUS,
			self::UNPASSABLE_RAIL => self::OPEN_MALUS,
			self::DANGER_FIRE => 8,
			self::DAMAGE_FIRE => 16,
			self::DANGER_OTHER => 8,
			self::DAMAGE_OTHER => self::BLOCKED_MALUS,
			self::DOOR_OPEN => self::OPEN_MALUS,
			self::DOOR_WOOD_CLOSED => self::BLOCKED_MALUS,
			self::DOOR_IRON_CLOSED => self::BLOCKED_MALUS,
			self::BREACH => 4,
			self::LEAVES => self::BLOCKED_MALUS,
			self::STICKY_HONEY => 8,
			self::COCOA => self::OPEN_MALUS
		};
	}
}