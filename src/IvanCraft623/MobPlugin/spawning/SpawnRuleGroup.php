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

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use function count;

final class SpawnRuleGroup{
	/** Vanilla's player distance range for a rule without a distance_filter. */
	public const DEFAULT_MIN_PLAYER_DISTANCE = 24.0;
	public const DEFAULT_MAX_PLAYER_DISTANCE = 128.0;

	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 * @phpstan-param array<string, int>   $permutations        entity identifier => weight
	 * @phpstan-param SpawnLiquid          $requiredLiquid      the liquid the feet must be in; NONE for land mobs
	 * @phpstan-param int                  $rarity              once picked, the group spawns 1 time in this many; 0 for always
	 * @phpstan-param float|null           $minPlayerDistance   blocks to the nearest player; null for no bound
	 * @phpstan-param float|null           $maxPlayerDistance   blocks to the nearest player; null for no bound
	 * @phpstan-param int|null             $surfaceDensityLimit most mobs of this type around a surface spawn; null for no limit
	 * @phpstan-param int|null             $caveDensityLimit    the same, underground
	 */
	public function __construct(
		private readonly array $conditions,
		private readonly int $weight = 1,
		private readonly int $herdMin = 1,
		private readonly int $herdMax = 1,
		private readonly array $permutations = [],
		private readonly SpawnLiquid $requiredLiquid = SpawnLiquid::NONE,
		private readonly int $rarity = 0,
		private readonly ?float $minPlayerDistance = self::DEFAULT_MIN_PLAYER_DISTANCE,
		private readonly ?float $maxPlayerDistance = self::DEFAULT_MAX_PLAYER_DISTANCE,
		private readonly ?int $surfaceDensityLimit = null,
		private readonly ?int $caveDensityLimit = null
	){
		if($herdMin < 1 || $herdMax < $herdMin){
			throw new \InvalidArgumentException("Invalid herd size range [$herdMin, $herdMax]");
		}
		if($rarity < 0){
			throw new \InvalidArgumentException("Rarity must not be negative, got $rarity");
		}
		if(($surfaceDensityLimit ?? 0) < 0 || ($caveDensityLimit ?? 0) < 0){
			throw new \InvalidArgumentException("Density limits must not be negative; use null for no limit");
		}
		if($minPlayerDistance !== null && $maxPlayerDistance !== null && $minPlayerDistance > $maxPlayerDistance){
			throw new \InvalidArgumentException("Invalid player distance range [$minPlayerDistance, $maxPlayerDistance]");
		}
	}

	/**
	 * @phpstan-return list<SpawnCondition>
	 */
	public function getConditions() : array{
		return $this->conditions;
	}

	public function getWeight() : int{
		return $this->weight;
	}

	public function getHerdMin() : int{
		return $this->herdMin;
	}

	public function getHerdMax() : int{
		return $this->herdMax;
	}

	/**
	 * @phpstan-return array<string, int>
	 */
	public function getPermutations() : array{
		return $this->permutations;
	}

	public function getRequiredLiquid() : SpawnLiquid{
		return $this->requiredLiquid;
	}

	public function getRarity() : int{
		return $this->rarity;
	}

	public function getMinPlayerDistance() : ?float{
		return $this->minPlayerDistance;
	}

	public function getMaxPlayerDistance() : ?float{
		return $this->maxPlayerDistance;
	}

	/**
	 * The most mobs of the rule's own type the region may hold in the band: at the limit
	 * the group doesn't spawn, and below it a herd is trimmed to what is left.
	 *
	 * @return int|null null for no limit
	 */
	public function getDensityLimit(SpawnBand $band) : ?int{
		return $band === SpawnBand::SURFACE ? $this->surfaceDensityLimit : $this->caveDensityLimit;
	}

	/**
	 * @phpstan-param list<SpawnCondition> $extra
	 */
	public function withConditions(array $extra) : self{
		if(count($extra) === 0){
			return $this;
		}

		return new self(
			conditions: [...$this->conditions, ...$extra],
			weight: $this->weight,
			herdMin: $this->herdMin,
			herdMax: $this->herdMax,
			permutations: $this->permutations,
			requiredLiquid: $this->requiredLiquid,
			rarity: $this->rarity,
			minPlayerDistance: $this->minPlayerDistance,
			maxPlayerDistance: $this->maxPlayerDistance,
			surfaceDensityLimit: $this->surfaceDensityLimit,
			caveDensityLimit: $this->caveDensityLimit
		);
	}

	/**
	 * Aquatic groups spawn only in their liquid, and land groups (which carry no "not in
	 * liquid" condition) only out of any liquid.
	 */
	public function admitsLiquid(SpawnLiquid $feetLiquid) : bool{
		return $feetLiquid === $this->requiredLiquid;
	}

	public function admitsPlayerDistance(float $distance) : bool{
		return ($this->minPlayerDistance === null || $distance >= $this->minPlayerDistance)
			&& ($this->maxPlayerDistance === null || $distance <= $this->maxPlayerDistance);
	}

	public function matches(SpawnConditionContext $ctx) : bool{
		if(!$this->admitsLiquid($ctx->getFeetLiquid()) || !$this->admitsPlayerDistance($ctx->getNearestPlayerDistance())){
			return false;
		}
		foreach($this->conditions as $condition){
			if(!$condition->test($ctx)){
				return false;
			}
		}

		return true;
	}
}
