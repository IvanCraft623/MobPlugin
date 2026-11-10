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

namespace IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\world;

use pocketmine\block\Block;
use pocketmine\world\World;

/**
 * Reads blocks through a {@link World}, on the main thread.
 */
class WorldBlockGetter extends BlockGetter{

	public function __construct(protected World $world){
		parent::__construct($world->getMinY(), $world->getMaxY());
	}

	public function getWorld() : World{
		return $this->world;
	}

	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true) : Block{
		return $this->world->getBlockAt($x, $y, $z, $cached, $addToCache);
	}
}