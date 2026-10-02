<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\spawning;

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\spawning\spawner\CandidateCache;
use IvanCraft623\MobPlugin\spawning\spawner\HerdSpawner;
use IvanCraft623\MobPlugin\spawning\spawner\SpawnSelector;
use IvanCraft623\MobPlugin\spawning\spawner\WorldSpawnPass;
use pocketmine\utils\Random;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function array_values;
use function count;

/**
 * Runs every tick on the main thread: each ticking chunk rolls vanilla's chance for one
 * column attempt.
 */
final class NaturalSpawner{
	/** Vanilla attempts a chunk when nextInt(2000) <= 10. */
	private const CHUNK_ROLL_BOUND = 2000;
	private const CHUNK_ROLL_MAX = 10;

	private ?CandidateCache $candidateCache = null;

	private int $cacheRevision = -1;

	private readonly SpawnSelector $selector;

	private readonly HerdSpawner $herdSpawner;

	/**
	 * @phpstan-param \Closure(World) : bool $isWorldEnabled whether the world takes part in spawning
	 */
	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly int $maxAttemptsPerTick,
		private readonly WorldManager $worldManager,
		private readonly \Closure $isWorldEnabled,
		private readonly Random $random = new Random()
	){
		$this->selector = new SpawnSelector($random, MobCategoryRegistry::getInstance());
		$this->herdSpawner = new HerdSpawner($registry, $random);
	}

	public function tick() : void{
		if($this->maxAttemptsPerTick < 1 || count($this->registry->getAll()) === 0){
			return;
		}

		/** @phpstan-var list<array{World, int}> $hits world and chunk hash */
		$hits = [];
		foreach($this->worldManager->getWorlds() as $world){
			// The ticking list is only kept up to date while chunk ticking is on.
			if($world->getChunkTickRadius() <= 0 || !($this->isWorldEnabled)($world)){
				continue;
			}
			foreach($world->getTickingChunks() as $chunkHash){
				if($this->random->nextBoundedInt(self::CHUNK_ROLL_BOUND) <= self::CHUNK_ROLL_MAX){
					$hits[] = [$world, $chunkHash];
				}
			}
		}
		$hitCount = count($hits);
		if($hitCount === 0){
			return;
		}
		if($hitCount > $this->maxAttemptsPerTick){
			// Keep a random subset.
			for($i = 0; $i < $this->maxAttemptsPerTick; $i++){
				$j = $i + $this->random->nextBoundedInt($hitCount - $i);
				[$hits[$i], $hits[$j]] = [$hits[$j], $hits[$i]];
			}
			$hitCount = $this->maxAttemptsPerTick;
		}

		// The revision is read once: rules registered mid-tick apply from the next tick.
		$candidateCache = $this->getCandidateCache();
		/** @phpstan-var array<int, WorldSpawnPass> $passes */
		$passes = [];
		CustomTimings::$naturalSpawning->startTiming();
		try{
			for($i = 0; $i < $hitCount; $i++){
				[$world, $chunkHash] = $hits[$i];
				$pass = $passes[$world->getId()] ??= new WorldSpawnPass($world, $candidateCache, $this->selector, $this->herdSpawner, $this->registry, $this->random);
				World::getXZ($chunkHash, $chunkX, $chunkZ);
				$pass->attempt($chunkX, $chunkZ);
			}
		}finally{
			CustomTimings::$naturalSpawning->stopTiming();
		}
	}

	private function getCandidateCache() : CandidateCache{
		$revision = $this->registry->getRevision();
		if($this->candidateCache === null || $revision !== $this->cacheRevision){
			$this->candidateCache = new CandidateCache(array_values($this->registry->getAll()), resolveTimings: CustomTimings::$naturalSpawningCandidateResolve);
			$this->cacheRevision = $revision;
		}

		return $this->candidateCache;
	}
}
