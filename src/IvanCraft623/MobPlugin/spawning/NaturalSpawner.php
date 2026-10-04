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
use IvanCraft623\MobPlugin\event\ChunkPreNaturalSpawnEvent;
use IvanCraft623\MobPlugin\Settings;
use IvanCraft623\MobPlugin\spawning\population\MobPopulation;
use IvanCraft623\MobPlugin\spawning\spawner\CandidateCache;
use IvanCraft623\MobPlugin\spawning\spawner\HerdSpawner;
use IvanCraft623\MobPlugin\spawning\spawner\SpawnSelector;
use IvanCraft623\MobPlugin\spawning\spawner\WorldSpawnPass;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Random;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function array_values;
use function count;
use function log;
use function max;
use const PHP_FLOAT_EPSILON;

/**
 * Runs every tick on the main thread: each ticking chunk rolls vanilla's chance for one
 * column attempt.
 */
final class NaturalSpawner{
	/** Vanilla attempts a chunk when nextInt(2000) <= 10. */
	private const CHUNK_ATTEMPT_CHANCE = 11 / 2000;

	private const MOB_COUNT_INTERVAL = 20;

	/** @phpstan-var array<int, int> world id => mobs, counted again every MOB_COUNT_INTERVAL ticks */
	private array $mobCounts = [];

	private int $ticksUntilMobCount = 0;

	private ?CandidateCache $candidateCache = null;

	/** The largest maximum player distance of any registered group; INF when one has no limit. */
	private float $maxPlayerDistance = 0.0;

	private int $cacheRevision = -1;

	private readonly SpawnSelector $selector;

	private readonly HerdSpawner $herdSpawner;

	private readonly MobPopulation $population;

	private readonly float $chunkGapScale;

	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly int $maxAttemptsPerTick,
		private readonly WorldManager $worldManager,
		private readonly Random $random = new Random(),
		?MobPopulation $population = null
	){
		if($maxAttemptsPerTick < 1){
			throw new \InvalidArgumentException("maxAttemptsPerTick must be at least 1, got $maxAttemptsPerTick");
		}
		$this->population = $population ?? MobPopulation::getInstance();
		$this->selector = new SpawnSelector($random, MobCategoryRegistry::getInstance());
		$this->herdSpawner = new HerdSpawner($registry, $random, $this->population->getBands());
		$this->chunkGapScale = 1 / log(1 - self::CHUNK_ATTEMPT_CHANCE);
	}

	public function tick() : void{
		if(count($this->registry->getAll()) === 0){
			return;
		}

		CustomTimings::$naturalSpawning->startTiming();
		try{
			$this->doTick();
		}finally{
			CustomTimings::$naturalSpawning->stopTiming();
		}
	}

	private function doTick() : void{
		// The revision is read once: rules registered mid-tick apply from the next tick.
		$candidateCache = $this->refreshRuleCaches();
		if(--$this->ticksUntilMobCount <= 0){
			$this->mobCounts = [];
			$this->ticksUntilMobCount = self::MOB_COUNT_INTERVAL;
		}
		/** @phpstan-var list<array{WorldSpawnPass, int, int}> $hits pass and chunk coordinates */
		$hits = [];
		foreach($this->worldManager->getWorlds() as $world){
			$settings = Settings::getSettings($world->getFolderName());
			// The ticking list is only kept up to date while chunk ticking is on.
			if($world->getChunkTickRadius() <= 0 || !$settings->isMobNaturalSpawningEnabled()){
				continue;
			}
			$players = self::getSpawningPlayers($world);
			if(count($players) === 0){
				continue; // nothing spawns without a player
			}
			$maxMobs = $settings->getMobNaturalSpawningMaxMobs();
			if($maxMobs > 0 && ($this->mobCounts[$world->getId()] ??= $this->countMobs($world)) >= $maxMobs){
				continue;
			}
			$chunks = $world->getTickingChunks();
			$chunkCount = count($chunks);
			$pass = null;
			// Every chunk rolls independently, so jumping from one hit to the next costs a
			// random number per hit instead of one per chunk.
			for($i = $this->nextChunkGap(); $i < $chunkCount; $i += 1 + $this->nextChunkGap()){
				$pass ??= new WorldSpawnPass($world, $chunks, $players, $candidateCache, $this->selector, $this->herdSpawner, $this->population, $this->registry, $this->random, $this->maxPlayerDistance);
				World::getXZ($chunks[$i], $chunkX, $chunkZ);
				// Decided here so a chunk that can't spawn takes none of the tick's budget.
				if(!$pass->canAttempt($chunkX, $chunkZ)){
					continue;
				}
				$event = new ChunkPreNaturalSpawnEvent($world, $chunkX, $chunkZ, $world->getChunk($chunkX, $chunkZ) ?? throw new AssumptionFailedError("Ticking chunks are loaded"));
				$event->call();
				if(!$event->isCancelled()){
					$hits[] = [$pass, $chunkX, $chunkZ];
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

		for($i = 0; $i < $hitCount; $i++){
			[$pass, $chunkX, $chunkZ] = $hits[$i];
			$pass->attempt($chunkX, $chunkZ);
		}
	}

	private function countMobs(World $world) : int{
		$count = 0;
		foreach($world->getEntities() as $entity){
			if($this->registry->get($entity::getNetworkTypeId()) !== null){
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @phpstan-return list<array{float, float, float}> the positions of the players that allow spawns
	 */
	private static function getSpawningPlayers(World $world) : array{
		$players = [];
		foreach($world->getPlayers() as $player){
			if(!$player->canBeCollidedWith()){
				continue; // spectators and the dead neither allow nor block spawns
			}
			$pos = $player->getPosition();
			$players[] = [$pos->x, $pos->y, $pos->z];
		}

		return $players;
	}

	/**
	 * How many chunks miss their roll before the next one hits: a geometric draw, the same
	 * distribution as rolling each chunk.
	 */
	private function nextChunkGap() : int{
		// The floor keeps log() finite; it is below the generator's resolution.
		return (int) (log(max(1.0 - $this->random->nextFloat(), PHP_FLOAT_EPSILON)) * $this->chunkGapScale);
	}

	/**
	 * Rebuilds what is derived from the registered rules when the registry changed.
	 */
	private function refreshRuleCaches() : CandidateCache{
		$revision = $this->registry->getRevision();
		if($this->candidateCache === null || $revision !== $this->cacheRevision){
			$rules = array_values($this->registry->getAll());
			$this->candidateCache = new CandidateCache($rules, resolveTimings: CustomTimings::$naturalSpawningCandidateResolve);
			$this->maxPlayerDistance = 0.0;
			foreach($rules as $spawnRules){
				foreach($spawnRules->getGroups() as $group){
					$this->maxPlayerDistance = max($this->maxPlayerDistance, $group->getMaxPlayerDistance());
				}
			}
			$this->cacheRevision = $revision;
		}

		return $this->candidateCache;
	}
}
