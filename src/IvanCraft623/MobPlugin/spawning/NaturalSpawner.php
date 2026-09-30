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
use IvanCraft623\MobPlugin\spawning\spawner\CandidateRule;
use IvanCraft623\MobPlugin\spawning\spawner\KeyContext;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function array_values;
use function count;
use function intdiv;
use function max;

/**
 * Natural spawner, all on the main thread. Orchestrates only:
 *
 * 1. Collect  — SpawnCollector samples positions around players (SpawnPosition).
 * 2. Shortlist — CandidateCache drops positions no rule set can match.
 * 3. Census   — SpawnCensus counts nearby mobs for the survivors only (RegionPopulation).
 * 4. Evaluate — SpawnEvaluator filters matching rule sets and picks one (SpawnRequest).
 * 5. Apply    — SpawnApplier re-validates the live world and spawns herds.
 */
final class NaturalSpawner{
	private SpawnCensus $census;

	private SpawnCollector $collector;

	private SpawnEvaluator $evaluator;

	private SpawnApplier $applier;

	private ?CandidateCache $candidateCache = null;

	private int $cacheRevision = -1;

	/** Server ticks seen so far; drives batching. */
	private int $ticks = 0;

	/** Rotates which worlds receive the remainder attempts, so no world is starved. */
	private int $rotation = 0;

	/**
	 * @param int $attemptsPerTick global column-sample budget per tick, split across
	 *                             spawn-eligible worlds
	 * @param int $batchInterval   run the pipeline every N ticks with N× the attempts. The
	 *                             census costs O(entities) per run regardless of how many positions it serves, so
	 *                             larger, rarer batches are cheaper per attempt (at the price of burstier spawning).
	 */
	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly int $attemptsPerTick,
		private readonly WorldManager $worldManager,
		private readonly int $batchInterval = 1,
		Random $random = new Random()
	){
		$this->census = new SpawnCensus(
			static fn(Entity $entity) : ?string => $registry->get($entity::getNetworkTypeId())?->getCategoryId()
		);
		$this->collector = new SpawnCollector($random);
		$this->evaluator = new SpawnEvaluator($random, MobCategoryRegistry::getInstance());
		$this->applier = new SpawnApplier($registry, MobCategoryRegistry::getInstance(), $worldManager, $random);
	}

	public function getRegistry() : SpawnRuleRegistry{
		return $this->registry;
	}

	/**
	 * Drives the whole loop; called once per server tick from the plugin scheduler.
	 */
	public function tick() : void{
		$this->ticks++;
		$interval = max(1, $this->batchInterval);
		if($this->ticks % $interval !== 0 || $this->attemptsPerTick < 1 || count($this->registry->getAll()) === 0){
			return;
		}

		$candidateCache = $this->getCandidateCache();
		$positions = $this->collect($this->attemptsPerTick * $interval);
		if(count($positions) === 0){
			return;
		}

		$candidates = $this->shortlistAndCount($positions, $candidateCache);
		if(count($candidates) === 0){
			return;
		}

		$requests = $this->evaluator->evaluate($candidates);
		if(count($requests) !== 0){
			$this->applier->apply($requests);
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

	/**
	 * Splits the global budget across spawn-eligible worlds (players online,
	 * non-peaceful), so adding worlds doesn't multiply the workload. The remainder after
	 * the equal share rotates between worlds run to run, so none is starved even when the
	 * budget is smaller than the world count.
	 *
	 * @phpstan-return list<SpawnPosition>
	 */
	private function collect(int $budget) : array{
		CustomTimings::$naturalSpawningCollect->startTiming();
		try{
			$eligible = [];
			foreach($this->worldManager->getWorlds() as $world){
				$players = $world->getPlayers();
				if(count($players) === 0 || $world->getDifficulty() === World::DIFFICULTY_PEACEFUL){
					continue;
				}
				$eligible[] = [$world, array_values($players)];
			}

			$positions = [];
			$n = count($eligible);
			if($n === 0){
				return $positions;
			}
			$shared = intdiv($budget, $n);
			$remainder = $budget % $n;
			$offset = $this->rotation++ % $n;
			foreach($eligible as $i => [$world, $players]){
				$attempts = $shared + ((($i + $offset) % $n) < $remainder ? 1 : 0);
				if($attempts < 1){
					continue;
				}
				foreach($this->collector->collect($world, $players, $attempts) as $position){
					$positions[] = $position;
				}
			}

			return $positions;
		}finally{
			CustomTimings::$naturalSpawningCollect->stopTiming();
		}
	}

	/**
	 * Drops positions no rule can spawn at, then runs the census (the most
	 * expensive step) only on the survivors, one entity pass per world.
	 *
	 * @phpstan-param list<SpawnPosition> $positions
	 *
	 * @phpstan-return list<SpawnCandidate>
	 */
	private function shortlistAndCount(array $positions, CandidateCache $candidateCache) : array{
		/** @phpstan-var array<int, list<array{SpawnPosition, non-empty-list<CandidateRule>}>> $byWorld */
		$byWorld = [];
		foreach($positions as $position){
			$viable = $candidateCache->getCandidates(new KeyContext($position->biomeId, $position->band, $position->difficulty, SpawnLiquid::fromBlockTypeId($position->feetTypeId)));
			if(count($viable) !== 0){
				$byWorld[$position->worldId][] = [$position, $viable];
			}
		}
		if(count($byWorld) === 0){
			return [];
		}

		CustomTimings::$naturalSpawningCensus->startTiming();
		try{
			$candidates = [];
			foreach($byWorld as $worldId => $survivors){
				$world = $this->worldManager->getWorld($worldId);
				if($world === null){
					continue;
				}
				$centers = [];
				foreach($survivors as [$position]){
					$centers[] = new Vector3($position->x + 0.5, $position->y, $position->z + 0.5);
				}
				$counts = $this->census->count($world, $centers);
				foreach($survivors as $i => [$position, $viable]){
					$candidates[] = new SpawnCandidate($position, $counts[$i], $viable);
				}
			}

			return $candidates;
		}finally{
			CustomTimings::$naturalSpawningCensus->stopTiming();
		}
	}
}
