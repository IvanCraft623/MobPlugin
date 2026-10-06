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

namespace IvanCraft623\MobPlugin\libs\_76575007da187bcf\IvanCraft623\Pathfinder\task;

use Closure;

use IvanCraft623\MobPlugin\libs\_76575007da187bcf\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_76575007da187bcf\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_76575007da187bcf\IvanCraft623\Pathfinder\PathFinder;
use IvanCraft623\MobPlugin\libs\_76575007da187bcf\IvanCraft623\Pathfinder\PathResult;
use IvanCraft623\MobPlugin\libs\_76575007da187bcf\IvanCraft623\Pathfinder\world\AsyncBlockGetter;

use pmmp\thread\ThreadSafeArray;
use pocketmine\math\Vector3;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\AsyncWorker;
use pocketmine\Server;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_unserialize;

class AsyncPathFinderTask extends AsyncTask {

	private const TLS_KEY_COMPLETION_CALLBACK = "completionCallback";

	/** Longest single wait for the main thread's answer, in microseconds; its notify ends the wait at once */
	private const CHUNK_WAIT_TIMEOUT = 50_000;

	/** Set by the main thread, unset by the worker once read */
	private string $missingChunkResult;

	private bool $cancelled = false;

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

	/**
	 * The completion callback then gets an empty path with {@link PathResult::CANCELLED}, and the search is skipped
	 * if it has not started.
	 */
	public function cancel() : void{
		$this->cancelled = true;
	}

	public function onRun() : void{
		if($this->cancelled) {
			return;
		}

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

	/**
	 * Asks the main thread for a chunk and blocks the worker until it answers.
	 *
	 * @return string|null the serialized chunk, empty if it is not available, null if the task was terminated
	 */
	public function requestChunk(int $chunkHash) : ?string{
		$this->publishProgress($chunkHash);
		//Progress is otherwise only looked at once per tick
		AsyncWorker::getNotifier()->wakeupSleeper();

		/** @var string|null $chunk */
		$chunk = $this->synchronized(function() : ?string{
			while(!isset($this->missingChunkResult)) {
				if($this->isTerminated()) {
					return null;
				}
				$this->wait(self::CHUNK_WAIT_TIMEOUT);
			}

			$chunk = $this->missingChunkResult;
			unset($this->missingChunkResult);
			return $chunk;
		});

		return $chunk;
	}

	public function onProgressUpdate($progress) : void{
		$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);

		/** @var int $progress */
		World::getXZ($progress, $chunkX, $chunkZ);
		$chunk = $world?->getChunk($chunkX, $chunkZ);
		$result = $chunk === null ? "" : FastChunkSerializer::serializeTerrain($chunk);

		$this->synchronized(function() use ($result) : void{
			$this->missingChunkResult = $result;
			$this->notify();
		});
	}

	public function onCompletion() : void{
		/** @var Closure $callback */
		$callback = $this->fetchLocal(self::TLS_KEY_COMPLETION_CALLBACK);
		if($this->cancelled) {
			/** @var Vector3 $target */
			$target = igbinary_unserialize($this->target);
			($callback)(new Path([], $target, PathResult::CANCELLED));
			return;
		}

		($callback)($this->getResult());
	}
}