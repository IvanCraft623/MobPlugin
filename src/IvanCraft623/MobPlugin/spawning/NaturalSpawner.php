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
use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\VanillaBiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\plan\SpawnRuleIndex;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function count;

/**
 * Three-stage natural spawner, all on the main thread: Collect (SpawnCollector
 * snapshots candidates) → Evaluate (SpawnEvaluator picks + tests via the planner
 * index) → Apply (SpawnApplier re-validates and spawns herds). This class only
 * orchestrates; the index is rebuilt only when the registry revision changes.
 */
final class NaturalSpawner{
	private SpawnCensus $census;

	private SpawnCollector $collector;

	private SpawnEvaluator $evaluator;

	private SpawnApplier $applier;

	private ?SpawnRuleIndex $index = null;

	private int $indexRevision = -1;

	public function __construct(
		private SpawnRuleRegistry $registry,
		private int $attemptsPerTick,
		private WorldManager $worldManager,
		private readonly BiomeTagResolver $biomeTags = new VanillaBiomeTagResolver()
	){
		$this->census = new SpawnCensus();
		$this->collector = new SpawnCollector($attemptsPerTick, $this->census);
		$this->evaluator = new SpawnEvaluator();
		$this->applier = new SpawnApplier($registry, $worldManager, $this->census);
	}

	public function getRegistry() : SpawnRuleRegistry{
		return $this->registry;
	}

	/**
	 * Drives the whole loop; called once per server tick from the plugin scheduler.
	 */
	public function tick() : void{
		if(count($this->registry->getSpawnEntries()) === 0 || $this->attemptsPerTick < 1){
			return;
		}

		$candidates = $this->collect();
		if(count($candidates) === 0){
			return;
		}

		CustomTimings::$naturalSpawningEvaluate->startTiming();
		try{
			$requests = $this->evaluator->evaluate($candidates, $this->getIndex());
		}finally{
			CustomTimings::$naturalSpawningEvaluate->stopTiming();
		}
		if(count($requests) !== 0){
			$this->applier->applyRequests($requests);
		}
	}

	/**
	 * The planner over the registered rule sets, folded once per registry revision.
	 */
	private function getIndex() : SpawnRuleIndex{
		$revision = $this->registry->getRevision();
		if($this->index === null || $revision !== $this->indexRevision){
			$rules = [];
			foreach($this->registry->getSpawnEntries() as $identifier => $entry){
				$rules[$identifier] = $entry->getRules();
			}
			$this->index = new SpawnRuleIndex($rules, $this->biomeTags);
			$this->indexRevision = $revision;
		}

		return $this->index;
	}

	/**
	 * Stage 1 — Collect.
	 *
	 * @phpstan-return list<SpawnCandidateSnapshot>
	 */
	private function collect() : array{
		CustomTimings::$naturalSpawningCollect->startTiming();
		try{
			$snapshots = [];
			foreach($this->worldManager->getWorlds() as $world){
				if(count($world->getPlayers()) === 0 || $world->getDifficulty() === World::DIFFICULTY_PEACEFUL){
					continue;
				}
				foreach($this->collector->collect($world) as $snapshot){
					$snapshots[] = $snapshot;
				}
			}

			return $snapshots;
		}finally{
			CustomTimings::$naturalSpawningCollect->stopTiming();
		}
	}
}
