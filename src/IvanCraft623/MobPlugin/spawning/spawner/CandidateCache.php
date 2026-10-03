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

namespace IvanCraft623\MobPlugin\spawning\spawner;

use IvanCraft623\MobPlugin\spawning\condition\CacheableConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\CompositeCondition;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use pocketmine\timings\TimingsHandler;
use function count;
use function implode;
use function spl_object_id;

/**
 * Partial evaluation of the rules per (biome, band, difficulty, feet liquid) key:
 * conditions the key decides are applied once, the rest stay as residuals.
 */
final class CandidateCache{
	/** @phpstan-var array<int, list<CandidateRule>> */
	private array $entries = [];

	/**
	 * Keys with equal results share them. Signatures are object ids, which stay unique
	 * while the pools hold the objects.
	 *
	 * @phpstan-var array<array-key, CandidateRule>
	 */
	private array $rulePool = [];

	/** @phpstan-var array<array-key, list<CandidateRule>> */
	private array $listPool = [];

	/**
	 * @phpstan-param list<SpawnRules> $rules
	 */
	public function __construct(
		private readonly array $rules,
		private readonly ?TimingsHandler $resolveTimings = null
	){}

	/**
	 * @phpstan-return list<CandidateRule>
	 */
	public function getCandidates(CacheableConditionContext $ctx) : array{
		$biomeId = $ctx->getBiomeId();
		$band = $ctx->getBand();
		$difficulty = $ctx->getDifficulty();
		$liquid = $ctx->getFeetLiquid();
		$key = self::hash($biomeId, $band, $difficulty, $liquid);

		return $this->entries[$key] ?? $this->resolve($key, new KeyContext($biomeId, $band, $difficulty, $liquid));
	}

	private static function hash(int $biomeId, SpawnBand $band, int $difficulty, SpawnLiquid $liquid) : int{
		if($biomeId < 0 || $difficulty < 0 || $difficulty > 3){
			throw new \InvalidArgumentException("Spawn key out of range: biome $biomeId, difficulty $difficulty");
		}

		return ($biomeId << 5) | ($difficulty << 3) | ($band->value << 2) | $liquid->value;
	}

	/**
	 * @phpstan-return list<CandidateRule>
	 */
	private function resolve(int $key, KeyContext $keyContext) : array{
		$this->resolveTimings?->startTiming();
		try{
			$candidates = [];
			foreach($this->rules as $rules){
				$candidate = $this->resolveRule($rules, $keyContext);
				if($candidate !== null){
					$candidates[] = $candidate;
				}
			}

			// Written last: a throwing condition leaves no partial entry behind.
			return $this->entries[$key] = $this->shareList($candidates);
		}finally{
			$this->resolveTimings?->stopTiming();
		}
	}

	private function resolveRule(SpawnRules $rules, KeyContext $keyContext) : ?CandidateRule{
		$liquid = $keyContext->getFeetLiquid();
		$groups = [];
		$signature = (string) spl_object_id($rules);
		foreach($rules->getGroups() as $group){
			if(!$group->admitsLiquid($liquid)){
				continue;
			}
			$residuals = [];
			foreach($group->getConditions() as $condition){
				$reduced = CompositeCondition::reduceCondition($condition, $keyContext);
				if($reduced === false){
					continue 2;
				}
				if($reduced !== true){
					$residuals[] = $reduced;
				}
			}
			$groups[] = [$group, $residuals];
			$signature .= ":" . spl_object_id($group);
			foreach($residuals as $condition){
				$signature .= "," . spl_object_id($condition);
			}
		}

		return count($groups) === 0 ? null : $this->rulePool[$signature] ??= new CandidateRule($rules, $groups);
	}

	/**
	 * @phpstan-param list<CandidateRule> $candidates
	 * @phpstan-return list<CandidateRule>
	 */
	private function shareList(array $candidates) : array{
		$ids = [];
		foreach($candidates as $candidate){
			$ids[] = spl_object_id($candidate);
		}

		return $this->listPool[implode(",", $ids)] ??= $candidates;
	}
}
