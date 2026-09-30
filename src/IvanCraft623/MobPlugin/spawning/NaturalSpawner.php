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
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function array_values;
use function count;

/**
 * Runs every tick on the main thread. Each tick spends attempts-per-tick attempts on the
 * players of every world, round-robin, so every player gets the same share over time.
 */
final class NaturalSpawner{
	private ?CandidateCache $candidateCache = null;

	private int $cacheRevision = -1;

	/** Index of the next anchor player, kept across ticks. */
	private int $cursor = 0;

	private readonly SpawnSelector $selector;

	private readonly HerdSpawner $herdSpawner;

	/**
	 * @phpstan-param \Closure(World) : bool $isWorldEnabled whether the world takes part in spawning
	 */
	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly int $attemptsPerTick,
		private readonly WorldManager $worldManager,
		private readonly \Closure $isWorldEnabled,
		private readonly Random $random = new Random()
	){
		$this->selector = new SpawnSelector($random, MobCategoryRegistry::getInstance());
		$this->herdSpawner = new HerdSpawner($registry, $random);
	}

	public function getRegistry() : SpawnRuleRegistry{
		return $this->registry;
	}

	public function tick() : void{
		if($this->attemptsPerTick < 1 || count($this->registry->getAll()) === 0){
			return;
		}

		/** @phpstan-var list<array{World, Player}> $anchors */
		$anchors = [];
		foreach($this->worldManager->getWorlds() as $world){
			if(!($this->isWorldEnabled)($world)){
				continue;
			}
			foreach($world->getPlayers() as $player){
				$anchors[] = [$world, $player];
			}
		}
		$anchorCount = count($anchors);
		if($anchorCount === 0){
			return;
		}

		/** @phpstan-var array<int, array{World, list<Player>}> $byWorld */
		$byWorld = [];
		for($i = 0; $i < $this->attemptsPerTick; $i++){
			[$world, $player] = $anchors[($this->cursor + $i) % $anchorCount];
			$byWorld[$world->getId()] ??= [$world, []];
			$byWorld[$world->getId()][1][] = $player;
		}
		$this->cursor = ($this->cursor + $this->attemptsPerTick) % $anchorCount;

		// The revision is read once: rules registered mid-tick apply from the next tick.
		$candidateCache = $this->getCandidateCache();
		CustomTimings::$naturalSpawning->startTiming();
		try{
			foreach($byWorld as [$world, $players]){
				$pass = new WorldSpawnPass($world, $candidateCache, $this->selector, $this->herdSpawner, $this->registry, $this->random);
				foreach($players as $player){
					$pass->attempt($player->getPosition());
				}
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
