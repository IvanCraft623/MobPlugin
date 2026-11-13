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

namespace IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\execution;

use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\task\AsyncPathFinderTask;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\world\Terrain;
use pmmp\thread\ThreadSafeArray;
use pocketmine\promise\PromiseResolver;
use pocketmine\scheduler\AsyncPool;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_serialize;
use function max;
use function min;

/**
 * Searches on a worker; chunks it was not sent are asked for to the main thread.
 */
final class AsyncSearchExecutor implements SearchExecutor{
	public function __construct(
		private AsyncPool $pool,
		private \Logger $logger
	){
	}

	public function search(NodeEvaluator $evaluator, MovementProfile $profile, Terrain $terrain, SearchRequest $request) : PathSearch{
		/** @phpstan-var PromiseResolver<Path> $resolver */
		$resolver = new PromiseResolver();
		$task = new AsyncPathFinderTask(
			igbinary_serialize($evaluator) ?? throw new \RuntimeException("Failed to serialize the evaluator"),
			igbinary_serialize($profile) ?? throw new \RuntimeException("Failed to serialize the profile"),
			igbinary_serialize($request) ?? throw new \RuntimeException("Failed to serialize the request"),
			ThreadSafeArray::fromArray($this->serializeChunksBetween($terrain, $request)),
			$terrain,
			$this->logger,
			static function(Path $path) use ($resolver) : void{
				$resolver->resolve($path);
			}
		);
		$this->pool->submitTask($task);
		return new PathSearch($resolver->getPromise(), $task->cancel(...));
	}

	/**
	 * @return string[] World::chunkHash() => serialized chunk, for the chunks between the start and the target
	 * @phpstan-return array<int, string>
	 */
	private function serializeChunksBetween(Terrain $terrain, SearchRequest $request) : array{
		$startX = $request->start->getFloorX() >> Chunk::COORD_BIT_SIZE;
		$startZ = $request->start->getFloorZ() >> Chunk::COORD_BIT_SIZE;
		$targetX = $request->target->getFloorX() >> Chunk::COORD_BIT_SIZE;
		$targetZ = $request->target->getFloorZ() >> Chunk::COORD_BIT_SIZE;
		$serialized = [];
		for ($x = min($startX, $targetX), $maxX = max($startX, $targetX); $x <= $maxX; ++$x) {
			for ($z = min($startZ, $targetZ), $maxZ = max($startZ, $targetZ); $z <= $maxZ; ++$z) {
				$chunk = $terrain->chunks->getChunk($x, $z);
				if ($chunk !== null) {
					$serialized[World::chunkHash($x, $z)] = FastChunkSerializer::serializeTerrain($chunk);
				}
			}
		}
		return $serialized;
	}
}