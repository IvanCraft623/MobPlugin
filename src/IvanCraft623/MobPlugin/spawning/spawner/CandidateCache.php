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
	 * Equal candidates are shared between keys, keyed by their objects' ids.
	 *
	 * @phpstan-var array<string, CandidateRule>
	 */
	private array $uniqueCandidates = [];

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
		$key = ($ctx->getBiomeId() << 5) | ($ctx->getDifficulty() << 3) | ($ctx->getBand()->value << 2) | $ctx->getFeetLiquid()->value;

		return $this->entries[$key] ?? $this->resolve($key, $ctx);
	}

	/**
	 * @phpstan-return list<CandidateRule>
	 */
	private function resolve(int $key, CacheableConditionContext $ctx) : array{
		$this->resolveTimings?->startTiming();
		try{
			$candidates = [];
			foreach($this->rules as $rules){
				$groups = [];
				$ids = [spl_object_id($rules)];
				foreach($rules->getGroups() as $group){
					$residuals = $group->reduce($ctx);
					if($residuals !== null){
						$groups[] = [$group, $residuals];
						$ids[] = spl_object_id($group);
						foreach($residuals as $condition){
							$ids[] = spl_object_id($condition);
						}
					}
				}
				if(count($groups) !== 0){
					$candidates[] = $this->uniqueCandidates[implode(",", $ids)] ??= new CandidateRule($rules, $groups);
				}
			}

			return $this->entries[$key] = $candidates;
		}finally{
			$this->resolveTimings?->stopTiming();
		}
	}
}
