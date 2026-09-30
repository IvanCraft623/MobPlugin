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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
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
	public const DEFAULT_MAX_KEYS = 4096;

	/** @phpstan-var array<int, list<CandidateRule>> */
	private array $entries = [];

	/** @phpstan-var array<array-key, CandidateRule> */
	private array $rulePool = [];

	/** @phpstan-var array<array-key, list<CandidateRule>> */
	private array $listPool = [];

	/**
	 * @phpstan-param list<SpawnRules> $rules
	 */
	public function __construct(
		private readonly array $rules,
		private readonly int $maxKeys = self::DEFAULT_MAX_KEYS,
		private readonly ?TimingsHandler $resolveTimings = null
	){
		if($maxKeys < 1){
			throw new \InvalidArgumentException("maxKeys must be at least 1");
		}
	}

	/**
	 * @phpstan-return list<CandidateRule>
	 */
	public function getCandidates(SpawnConditionContext $ctx) : array{
		$key = self::hash($ctx->getBiomeId(), $ctx->getBand(), $ctx->getDifficulty(), $ctx->getFeetLiquid());

		return $this->entries[$key] ?? $this->resolve($key, KeyContext::from($ctx));
	}

	public function clear() : void{
		$this->entries = [];
		$this->rulePool = [];
		$this->listPool = [];
	}

	public function getSize() : int{
		return count($this->entries);
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
			if(count($this->entries) >= $this->maxKeys){
				$this->clear();
			}
			$candidates = [];
			foreach($this->rules as $rules){
				$candidate = $this->resolveRule($rules, $keyContext);
				if($candidate !== null){
					$candidates[] = $candidate;
				}
			}

			// Written last: a throwing condition leaves no partial entry behind.
			return $this->entries[$key] = $this->internList($candidates);
		}finally{
			$this->resolveTimings?->stopTiming();
		}
	}

	private function resolveRule(SpawnRules $rules, KeyContext $keyContext) : ?CandidateRule{
		$liquid = $keyContext->getFeetLiquid();
		$groups = [];
		$signature = (string) spl_object_id($rules);
		foreach($rules->getGroups() as $group){
			// Land rules carry no "not in liquid" condition, so liquid keys only admit groups that ask for it.
			if($liquid !== SpawnLiquid::NONE && $group->getRequiredLiquid() !== $liquid){
				continue;
			}
			$residuals = [];
			// Run last: counting the region is the most expensive read of an attempt.
			$populationResiduals = [];
			foreach($group->getConditions() as $condition){
				if($condition->isCacheable()){
					try{
						if(!$condition->test($keyContext)){
							continue 2;
						}
						continue;
					}catch(PointInputRequired $e){
						// Reads a per-attempt value: decided on each attempt.
						if($e->isPopulation()){
							$populationResiduals[] = $condition;
							continue;
						}
					}
				}
				$residuals[] = $condition;
			}
			$residuals = [...$residuals, ...$populationResiduals];
			$groupSignature = ":" . spl_object_id($group);
			foreach($residuals as $condition){
				$groupSignature .= "," . spl_object_id($condition);
			}
			$groups[] = [$group, $residuals];
			$signature .= $groupSignature;
		}
		if(count($groups) === 0){
			return null;
		}

		return $this->rulePool[$signature] ??= new CandidateRule($rules, $groups);
	}

	/**
	 * @phpstan-param list<CandidateRule> $candidates
	 * @phpstan-return list<CandidateRule>
	 */
	private function internList(array $candidates) : array{
		$ids = [];
		foreach($candidates as $candidate){
			$ids[] = spl_object_id($candidate);
		}

		return $this->listPool[implode(",", $ids)] ??= $candidates;
	}
}
