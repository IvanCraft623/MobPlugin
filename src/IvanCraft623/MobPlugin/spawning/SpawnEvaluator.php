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
use IvanCraft623\MobPlugin\spawning\spawner\SnapshotContext;
use pocketmine\utils\Random;
use function count;

/**
 * Stage 2 — Evaluate. Pure: reads only the candidate (position, census counts, viable
 * rule sets), the injected Random and the category registry, never a live World or a registry.
 *
 * Filter, then pick (Bedrock semantics — `weight` belongs to each condition group, so it
 * can only be weighed once the group has matched): every viable rule set whose category
 * is under its population cap runs its conditions; each rule set contributes its first
 * matching group, weighted by that group's weight; one match is picked, then the
 * category cap's probability roll decides the spawn.
 */
final class SpawnEvaluator{

	public function __construct(
		private readonly Random $random,
		private readonly MobCategoryRegistry $categories
	){}

	/**
	 * @phpstan-param list<SpawnCandidate> $candidates
	 *
	 * @phpstan-return list<SpawnRequest>
	 */
	public function evaluate(array $candidates) : array{
		CustomTimings::$naturalSpawningEvaluate->startTiming();
		try{
			$requests = [];
			foreach($candidates as $candidate){
				$request = $this->evaluateOne($candidate);
				if($request !== null){
					$requests[] = $request;
				}
			}

			return $requests;
		}finally{
			CustomTimings::$naturalSpawningEvaluate->stopTiming();
		}
	}

	public function evaluateOne(SpawnCandidate $candidate) : ?SpawnRequest{
		$position = $candidate->position;
		$population = $candidate->population;
		$band = $position->band;
		$ctx = new SnapshotContext($position, $population);

		/** @phpstan-var list<array{SpawnRules, SpawnRuleGroup, int, int}> $matches rules, group, category count, cap */
		$matches = [];
		$totalWeight = 0;
		foreach($candidate->viable as $candidateRule){
			$rules = $candidateRule->getRules();
			$category = $this->categories->get($rules->getCategoryId());
			if($category === null){
				continue;
			}
			$cap = $category->getCap($band);
			$categoryCount = $population->getCategoryCount($category->id, $band);
			if($categoryCount >= $cap){
				continue; // capped categories don't compete
			}
			$group = $candidateRule->match($ctx);
			if($group === null || $group->getWeight() <= 0){
				continue;
			}
			$matches[] = [$rules, $group, $categoryCount, $cap];
			$totalWeight += $group->getWeight();
		}
		if(count($matches) === 0){
			return null;
		}

		[$rules, $group, $categoryCount, $cap] = $this->pick($matches, $totalWeight);

		// Population cap roll: the fuller the category's region, the likelier the attempt
		// is dropped.
		if($this->random->nextFloat() * $cap >= $cap - $categoryCount){
			return null;
		}

		return new SpawnRequest(
			$position,
			$rules,
			$group,
			categoryCount: $categoryCount,
			densityCount: $population->getIdentifierCount($rules->getIdentifier(), $band)
		);
	}

	/**
	 * @phpstan-param non-empty-list<array{SpawnRules, SpawnRuleGroup, int, int}> $matches
	 *
	 * @phpstan-return array{SpawnRules, SpawnRuleGroup, int, int}
	 */
	private function pick(array $matches, int $totalWeight) : array{
		$roll = $this->random->nextBoundedInt($totalWeight);
		foreach($matches as $match){
			$roll -= $match[1]->getWeight();
			if($roll < 0){
				return $match;
			}
		}

		return $matches[count($matches) - 1];
	}
}
