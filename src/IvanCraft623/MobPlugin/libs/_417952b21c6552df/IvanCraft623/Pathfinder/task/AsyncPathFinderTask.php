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

namespace IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\task;

use Closure;

use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\PathFinder;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\world\AsyncBlockGetter;

use pmmp\thread\ThreadSafeArray;
use pocketmine\math\Vector3;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_unserialize;

class AsyncPathFinderTask extends AsyncTask {

	private const TLS_KEY_COMPLETION_CALLBACK = "completionCallback";

	public string $missingChunkResult;

	/**
	 * @phpstan-param ThreadSafeArray<int, string> $defaultChunks
	 * @phpstan-param Closure(Path $path) : void $onCompletion
	 */
	public function __construct(
		private string $nodeEvaluator,
		private string $start,
		private string $target,
		private int $worldId,
		private int $maxVisitedNodes,
		private float $maxDistanceFromStart,
		private int $reachRange,
		private ThreadSafeArray $defaultChunks,
		private int $worldMinY,
		private int $worldMaxY,
		Closure $onCompletion,
	){
		$this->storeLocal(self::TLS_KEY_COMPLETION_CALLBACK, $onCompletion);
	}

	public function onRun() : void{
		/** @var NodeEvaluator */
		$evaluator = igbinary_unserialize($this->nodeEvaluator);
		$blockGetter = new AsyncBlockGetter($this, $this->worldMinY, $this->worldMaxY);

		/** @var Vector3 */
		$start = igbinary_unserialize($this->start);
		/** @var Vector3 */
		$target = igbinary_unserialize($this->target);

		foreach($this->defaultChunks as $hash => $chunk) {
			World::getXZ($hash, $chunkX, $chunkZ);
			$blockGetter->setChunk($chunkX, $chunkZ, FastChunkSerializer::deserializeTerrain($chunk));
		}

		$evaluator->prepare($blockGetter, $start);

		$this->setResult(PathFinder::actuallyFindPath(
			$evaluator,
			$evaluator->getStart(),
			$evaluator->getGoal((int) $target->x, (int) $target->y, (int) $target->z),
			$this->maxVisitedNodes,
			$this->maxDistanceFromStart,
			$this->reachRange
		));
	}

	public function onProgressUpdate($progress) : void{
		$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);

		if($world === null) {
			$this->missingChunkResult = "";
		} else {
			/** @var int $progress */
			World::getXZ($progress, $chunkX, $chunkZ);
			$chunk = $world->getChunk($chunkX, $chunkZ);
			if($chunk === null) {
				$this->missingChunkResult = "";
			} else {
				$this->missingChunkResult = FastChunkSerializer::serializeTerrain($chunk);
			}
		}
	}

	public function onCompletion() : void{
		/** @var Closure $callback */
		$callback = $this->fetchLocal(self::TLS_KEY_COMPLETION_CALLBACK);
		($callback)($this->getResult());
	}
}