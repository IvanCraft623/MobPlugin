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

namespace IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\world;

use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function array_key_exists;

/**
 * @phpstan-import-type ChunkPosHash from World
 */
final class RequestingChunkSource implements ChunkSource{
	/**
	 * @var Chunk[]|null[] World::chunkHash() => chunk, null if not available
	 * @phpstan-var array<ChunkPosHash, ?Chunk>
	 */
	private array $requested = [];

	public function __construct(
		private ChunkSource $source,
		private ChunkRequester $requester
	){
	}

	public function getChunk(int $chunkX, int $chunkZ) : ?Chunk{
		$chunk = $this->source->getChunk($chunkX, $chunkZ);
		if ($chunk !== null) {
			return $chunk;
		}
		$hash = World::chunkHash($chunkX, $chunkZ);
		if (!array_key_exists($hash, $this->requested)) {
			$this->requested[$hash] = $this->requester->requestChunk($chunkX, $chunkZ);
		}
		return $this->requested[$hash];
	}
}