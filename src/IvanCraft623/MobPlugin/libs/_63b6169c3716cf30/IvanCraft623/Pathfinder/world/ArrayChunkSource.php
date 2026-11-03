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

namespace IvanCraft623\MobPlugin\libs\_63b6169c3716cf30\IvanCraft623\Pathfinder\world;

use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * @phpstan-import-type ChunkPosHash from World
 */
final class ArrayChunkSource implements ChunkSource{
	/**
	 * @param Chunk[] $chunks World::chunkHash() => chunk
	 * @phpstan-param array<ChunkPosHash, Chunk> $chunks
	 */
	public function __construct(private array $chunks = []){
	}

	public function getChunk(int $chunkX, int $chunkZ) : ?Chunk{
		return $this->chunks[World::chunkHash($chunkX, $chunkZ)] ?? null;
	}

	public function setChunk(int $chunkX, int $chunkZ, Chunk $chunk) : void{
		$this->chunks[World::chunkHash($chunkX, $chunkZ)] = $chunk;
	}
}