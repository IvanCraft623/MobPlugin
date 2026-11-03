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

namespace IvanCraft623\MobPlugin\libs\_d231130ad4236385\IvanCraft623\Pathfinder\world;

use pocketmine\world\World;

/**
 * Chunks a search may read, on the thread that starts it.
 */
final class Terrain{
	public function __construct(
		public readonly ChunkSource $chunks,
		public readonly int $minY,
		public readonly int $maxY
	){
	}

	public static function fromWorld(World $world) : self{
		return new self(new WorldChunkSource($world), $world->getMinY(), $world->getMaxY());
	}

	public function createBlockGetter() : ChunkBlockGetter{
		return new ChunkBlockGetter($this->chunks, $this->minY, $this->maxY);
	}
}