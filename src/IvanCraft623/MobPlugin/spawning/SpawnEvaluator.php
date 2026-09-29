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
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;
use pocketmine\utils\Random;
use function count;

/**
 * Stage 2 — Evaluate. Pure: reads only the candidate (position, census counts, viable
 * bindings) and the injected Random, never a live World or a registry.
 *
 * Filter, then pick (Bedrock semantics — `weight` belongs to each condition group, so it
 * can only be weighed once the group has matched): every viable rule set whose category
 * is under its population cap runs its conditions; each rule set contributes its first
 * matching group, weighted by that group's weight; one match is picked, then the
 * category cap's probability roll decides the spawn.
 */
final class SpawnEvaluator{

	public function __construct(
		private readonly Random $random
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
		$counts = $candidate->counts;
		$band = $position->band;
		$ctx = new SpawnConditionContext(
			env: new CandidateSpawnEnvironment($position, $counts),
			x: $position->x,
			y: $position->y,
			z: $position->z,
			band: $band,
			difficulty: $position->difficulty,
			weatherLightPenalty: $position->weatherLightPenalty,
			nearestPlayerDistance: $position->nearestPlayerDistance
		);

		/** @phpstan-var list<array{SpawnRuleBinding, SpawnConditionGroup, int}> $matches binding, group, category count */
		$matches = [];
		$totalWeight = 0;
		foreach($candidate->viable as $binding){
			$category = $binding->getCategory();
			$categoryCount = $counts->category($category->id, $band);
			if($categoryCount >= $category->getPopulationCaps()->get($band)){
				continue; // capped categories don't compete
			}
			$group = $binding->getRules()->check($ctx);
			if($group === null || $group->getWeight() <= 0){
				continue;
			}
			$matches[] = [$binding, $group, $categoryCount];
			$totalWeight += $group->getWeight();
		}
		if(count($matches) === 0){
			return null;
		}

		[$binding, $group, $categoryCount] = $this->pick($matches, $totalWeight);

		// Population cap roll: the fuller the category's region, the likelier the attempt
		// is dropped.
		$cap = $binding->getCategory()->getPopulationCaps()->get($band);
		if($this->random->nextFloat() * $cap >= $cap - $categoryCount){
			return null;
		}

		return new SpawnRequest(
			$position,
			$binding,
			$group,
			categoryCount: $categoryCount,
			densityCount: $counts->identifier($binding->getRules()->getIdentifier(), $band)
		);
	}

	/**
	 * @phpstan-param non-empty-list<array{SpawnRuleBinding, SpawnConditionGroup, int}> $matches
	 *
	 * @phpstan-return array{SpawnRuleBinding, SpawnConditionGroup, int}
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
