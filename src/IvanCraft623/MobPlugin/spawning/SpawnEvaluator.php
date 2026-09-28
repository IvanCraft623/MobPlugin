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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\plan\SpawnRuleIndex;

use function array_values;
use function count;
use function max;
use function mt_getrandmax;
use function mt_rand;

/**
 * Stage 2 — Evaluate. Weighted-picks one rule per candidate via the planner index and
 * runs its conditions against the snapshot. Pure: never reads a live World.
 */
final class SpawnEvaluator{

	/**
	 * Shortlist via the index, pick one rule by weight, its conditions decide (fail =
	 * no spawn, no fallback), then the category population cap gates the spawn.
	 *
	 * @phpstan-param list<SpawnCandidateSnapshot> $candidates
	 *
	 * @phpstan-return list<SpawnRequest>
	 */
	public function evaluate(array $candidates, SpawnRuleIndex $index) : array{
		$requests = [];
		foreach($candidates as $candidate){
			$band = $candidate->band;
			$viable = $index->candidatesFor($candidate->biomeId, $band, $candidate->difficulty, $candidate->blockTypeId);
			if(count($viable) === 0){
				continue;
			}

			$env = new SnapshotSpawnEnvironment($candidate);
			$chosen = self::weightedPick($viable);
			$result = $this->attemptSpawn($chosen, $candidate, $env, $band);
			if($result === null){
				continue;
			}
			[$match, $categoryCount] = $result;
			$requests[] = new SpawnRequest(
				$candidate->worldId,
				$candidate->x,
				$candidate->y,
				$candidate->z,
				$match,
				categoryCount: $categoryCount,
				band: $band
			);
		}

		return $requests;
	}

	/**
	 * The picked rule's conditions, then the category population cap with the vanilla
	 * probability formula. Per-mob caps live in the data as density_limit conditions.
	 *
	 * @phpstan-return array{SpawnConditionMatch, int}|null — the match and the category
	 *     population count that passed the cap gate (threaded to the applier so it can
	 *     re-check without rescanning the world).
	 */
	private function attemptSpawn(SpawnRules $rule, SpawnCandidateSnapshot $candidate, SnapshotSpawnEnvironment $env, SpawnBand $band) : ?array{
		$category = MobCategoryRegistry::getInstance()->get($rule->getCategoryId());
		if($category === null){
			return null;
		}

		$ctx = new SpawnConditionContext(
			env: $env,
			x: $candidate->x,
			y: $candidate->y,
			z: $candidate->z,
			band: $band,
			difficulty: $candidate->difficulty,
			weatherLightPenalty: $candidate->weatherLightPenalty,
			nearestPlayerDistance: $candidate->nearestPlayerDistance
		);
		$group = $rule->check($ctx);
		if($group === null){
			return null;
		}

		$cap = $category->getPopulationCaps()->get($band);
		$count = $env->countNearbyCategory($category);
		if($count >= $cap){
			return null;
		}
		if(mt_rand() / mt_getrandmax() > ($cap - $count) / $cap){
			return null;
		}

		return [new SpawnConditionMatch($rule->getIdentifier(), $group), $count];
	}

	/**
	 * Weighted by the rule's pick weight; a failed pick is not retried.
	 *
	 * @phpstan-param list<SpawnRules> $rules
	 */
	private static function weightedPick(array $rules) : SpawnRules{
		$total = 0;
		foreach($rules as $rule){
			$total += $rule->getPickWeight();
		}
		$roll = mt_rand(1, max(1, $total));
		foreach($rules as $rule){
			$roll -= $rule->getPickWeight();
			if($roll <= 0){
				return $rule;
			}
		}

		return array_values($rules)[count($rules) - 1];
	}
}
