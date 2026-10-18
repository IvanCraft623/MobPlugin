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

use IvanCraft623\MobPlugin\spawning\condition\CacheableConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\CompositeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use function array_keys;
use function count;

final class SpawnRuleGroup{
	/** @phpstan-var non-empty-list<SpawnHerd> */
	private readonly array $herds;

	/** Vanilla's player distance range for a rule without a distance_filter. */
	public const DEFAULT_MIN_PLAYER_DISTANCE = 24.0;
	public const DEFAULT_MAX_PLAYER_DISTANCE = 128.0;

	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 * @phpstan-param list<SpawnHerd>      $herds               the ways the mobs spawn together; one is picked per spawn, one mob alone when empty
	 * @phpstan-param list<SpawnPermutation> $permutations      the mobs each herd member may spawn as
	 * @phpstan-param SpawnLiquid          $requiredLiquid      the liquid the feet must be in; NONE for land mobs
	 * @phpstan-param int                  $rarity              once picked, the group spawns 1 time in this many; 0 for always
	 * @phpstan-param float                $minPlayerDistance   blocks to the nearest player
	 * @phpstan-param float                $maxPlayerDistance   blocks to the nearest player; INF for no bound
	 * @phpstan-param int|null             $surfaceDensityLimit most mobs of this type around a surface spawn; null for no limit
	 * @phpstan-param int|null             $caveDensityLimit    the same, underground
	 */
	public function __construct(
		private readonly array $conditions,
		private readonly int $weight = 1,
		array $herds = [],
		private readonly array $permutations = [],
		private readonly SpawnLiquid $requiredLiquid = SpawnLiquid::NONE,
		private readonly int $rarity = 0,
		private readonly float $minPlayerDistance = self::DEFAULT_MIN_PLAYER_DISTANCE,
		private readonly float $maxPlayerDistance = self::DEFAULT_MAX_PLAYER_DISTANCE,
		private readonly ?int $surfaceDensityLimit = null,
		private readonly ?int $caveDensityLimit = null
	){
		if($weight < 0){
			throw new \InvalidArgumentException("Weight must not be negative, got $weight");
		}
		$this->herds = count($herds) === 0 ? [new SpawnHerd()] : $herds;
		if($rarity < 0){
			throw new \InvalidArgumentException("Rarity must not be negative, got $rarity");
		}
		if(($surfaceDensityLimit ?? 0) < 0 || ($caveDensityLimit ?? 0) < 0){
			throw new \InvalidArgumentException("Density limits must not be negative; use null for no limit");
		}
		if($minPlayerDistance > $maxPlayerDistance){
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

	/**
	 * @phpstan-return non-empty-list<SpawnHerd>
	 */
	public function getHerds() : array{
		return $this->herds;
	}

	/**
	 * @phpstan-return list<SpawnPermutation>
	 */
	public function getPermutations() : array{
		return $this->permutations;
	}

	/**
	 * @phpstan-return list<string> the spawn events the group's mobs are sent
	 */
	public function getEvents() : array{
		$events = [];
		foreach($this->herds as $herd){
			$event = $herd->getEvent();
			if($event !== null){
				$events[$event] = true;
			}
			$initialEvent = $herd->getInitialEvent();
			if($initialEvent !== null){
				$events[$initialEvent] = true;
			}
		}
		foreach($this->permutations as $permutation){
			$event = $permutation->getEvent();
			if($event !== null){
				$events[$event] = true;
			}
		}

		return array_keys($events);
	}

	public function getRequiredLiquid() : SpawnLiquid{
		return $this->requiredLiquid;
	}

	public function getRarity() : int{
		return $this->rarity;
	}

	public function getMinPlayerDistance() : float{
		return $this->minPlayerDistance;
	}

	public function getMaxPlayerDistance() : float{
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
			herds: $this->herds,
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

	/**
	 * @internal
	 * @phpstan-return list<SpawnCondition>|null the conditions the key leaves undecided; null when it rules the group out
	 */
	public function reduce(CacheableConditionContext $ctx) : ?array{
		if(!$this->admitsLiquid($ctx->getFeetLiquid())){
			return null;
		}
		$residuals = [];
		foreach($this->conditions as $condition){
			$reduced = CompositeCondition::reduceCondition($condition, $ctx);
			if($reduced === false){
				return null;
			}
			if($reduced !== true){
				$residuals[] = $reduced;
			}
		}

		return $residuals;
	}

	public function admitsPlayerDistance(float $distance) : bool{
		return $distance >= $this->minPlayerDistance && $distance <= $this->maxPlayerDistance;
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