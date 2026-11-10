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

namespace IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\task;

use Closure;

use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\AStar;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\PathResult;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\world\ArrayChunkSource;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\world\ChunkBlockGetter;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\world\ChunkRequester;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\world\RequestingChunkSource;
use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\IvanCraft623\Pathfinder\world\Terrain;

use pmmp\thread\ThreadSafeArray;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\AsyncWorker;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function igbinary_unserialize;

class AsyncPathFinderTask extends AsyncTask implements ChunkRequester {

	private const TLS_KEY_COMPLETION_CALLBACK = "completionCallback";
	private const TLS_KEY_TERRAIN = "terrain";
	private const TLS_KEY_LOGGER = "logger";

	/** Longest single wait for the main thread's answer, in microseconds; its notify ends the wait at once */
	private const CHUNK_WAIT_TIMEOUT = 50_000;

	/** Set by the main thread, unset by the worker once read */
	private string $missingChunkResult;

	private bool $cancelled = false;

	private ?string $failure = null; //what the search threw

	private int $minY;
	private int $maxY;

	/**
	 * @param string $nodeEvaluator serialized
	 * @param string $profile       serialized {@link MovementProfile}
	 * @param string $request       serialized {@link SearchRequest}
	 * @phpstan-param ThreadSafeArray<int, string> $sentChunks World::chunkHash() => serialized chunk
	 * @phpstan-param Closure(Path $path) : void $onCompletion
	 */
	public function __construct(
		private string $nodeEvaluator,
		private string $profile,
		private string $request,
		private ThreadSafeArray $sentChunks,
		Terrain $terrain, //answers the chunk requests
		\Logger $logger,
		Closure $onCompletion,
	){
		$this->minY = $terrain->minY;
		$this->maxY = $terrain->maxY;
		$this->storeLocal(self::TLS_KEY_COMPLETION_CALLBACK, $onCompletion);
		$this->storeLocal(self::TLS_KEY_TERRAIN, $terrain);
		$this->storeLocal(self::TLS_KEY_LOGGER, $logger);
	}

	/**
	 * The completion callback then gets an empty {@link PathResult::CANCELLED} path.
	 */
	public function cancel() : void{
		$this->cancelled = true;
	}

	public function onRun() : void{
		if($this->cancelled) {
			return;
		}

		//a throw here kills the worker and crashes the server
		try{
			$this->setResult($this->search());
		}catch(\Throwable $e){
			$this->failure = (string) $e;
		}
	}

	private function search() : Path{
		/** @var NodeEvaluator $evaluator */
		$evaluator = igbinary_unserialize($this->nodeEvaluator);

		$chunks = new ArrayChunkSource();
		foreach($this->sentChunks as $hash => $chunk) {
			World::getXZ($hash, $chunkX, $chunkZ);
			$chunks->setChunk($chunkX, $chunkZ, FastChunkSerializer::deserializeTerrain($chunk));
		}
		$blockGetter = new ChunkBlockGetter(new RequestingChunkSource($chunks, $this), $this->minY, $this->maxY);

		/** @var MovementProfile $profile */
		$profile = igbinary_unserialize($this->profile);

		return (new AStar($evaluator, $profile, $blockGetter))->search($this->getRequest(), fn() : bool => $this->cancelled);
	}

	private function getRequest() : SearchRequest{
		/** @var SearchRequest */
		return igbinary_unserialize($this->request);
	}

	public function requestChunk(int $chunkX, int $chunkZ) : ?Chunk{
		$this->publishProgress(World::chunkHash($chunkX, $chunkZ));
		//Progress is otherwise only looked at once per tick
		AsyncWorker::getNotifier()->wakeupSleeper();

		/** @var string $chunk */
		$chunk = $this->synchronized(function() : string{
			while(!isset($this->missingChunkResult)) {
				$this->wait(self::CHUNK_WAIT_TIMEOUT);
			}

			$chunk = $this->missingChunkResult;
			unset($this->missingChunkResult);
			return $chunk;
		});

		return $chunk === "" ? null : FastChunkSerializer::deserializeTerrain($chunk);
	}

	public function onProgressUpdate($progress) : void{
		/** @var Terrain $terrain */
		$terrain = $this->fetchLocal(self::TLS_KEY_TERRAIN);
		/** @var int $progress */
		World::getXZ($progress, $chunkX, $chunkZ);
		$chunk = $terrain->chunks->getChunk($chunkX, $chunkZ);
		$result = $chunk === null ? "" : FastChunkSerializer::serializeTerrain($chunk);

		$this->synchronized(function() use ($result) : void{
			$this->missingChunkResult = $result;
			$this->notify();
		});
	}

	public function onCompletion() : void{
		/** @phpstan-var Closure(Path) : void $callback */
		$callback = $this->fetchLocal(self::TLS_KEY_COMPLETION_CALLBACK);
		if($this->cancelled) {
			($callback)($this->emptyPath(PathResult::CANCELLED));
			return;
		}

		$result = $this->getResult();
		if(!$result instanceof Path) {
			/** @var \Logger $logger */
			$logger = $this->fetchLocal(self::TLS_KEY_LOGGER);
			$logger->error("Path search failed: " . ($this->failure ?? "no result"));
			($callback)($this->emptyPath(PathResult::FAILED));
			return;
		}
		($callback)($result);
	}

	private function emptyPath(PathResult $result) : Path{
		return new Path([], $this->getRequest()->target, $result);
	}
}