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

namespace IvanCraft623\MobPlugin\spawning\parse;

use IvanCraft623\MobPlugin\spawning\condition\BandCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use function array_values;
use function count;

/**
 * Filled by the component parsers of one condition object, in any order: payloads are
 * last-write, conditions accumulate, liquids must agree.
 */
final class SpawnRuleGroupBuilder{
	/** @phpstan-var list<SpawnCondition> */
	private array $conditions = [];

	private int $weight = 1;

	private int $rarity = 0;

	private float $minPlayerDistance = SpawnRuleGroup::DEFAULT_MIN_PLAYER_DISTANCE;

	private float $maxPlayerDistance = SpawnRuleGroup::DEFAULT_MAX_PLAYER_DISTANCE;

	private ?int $surfaceDensityLimit = null;

	private ?int $caveDensityLimit = null;

	private int $herdMin = 1;

	private int $herdMax = 1;

	/** @phpstan-var array<string, int> */
	private array $permutations = [];

	/** @phpstan-var array<int, SpawnBand> */
	private array $habitatBands = [];

	private SpawnLiquid $liquid = SpawnLiquid::NONE;

	private bool $neverSpawns = false;

	public function __construct(
		private readonly string $identifier
	){}

	public function getIdentifier() : string{
		return $this->identifier;
	}

	public function addCondition(SpawnCondition $condition) : void{
		$this->conditions[] = $condition;
	}

	public function allowHabitatBand(SpawnBand $band) : void{
		$this->habitatBands[$band->value] = $band;
	}

	/**
	 * @phpstan-throws \InvalidArgumentException when another liquid is already required
	 */
	public function setLiquid(SpawnLiquid $liquid) : void{
		if($this->liquid !== SpawnLiquid::NONE && $this->liquid !== $liquid){
			throw new \InvalidArgumentException("a group can't require both {$this->liquid->name} and {$liquid->name}");
		}
		$this->liquid = $liquid;
	}

	/**
	 * Drops the whole group at build time, e.g. for a component that isn't implemented.
	 */
	public function markNeverSpawns() : void{
		$this->neverSpawns = true;
	}

	public function setWeight(int $weight) : void{
		$this->weight = $weight;
	}

	public function setRarity(int $rarity) : void{
		$this->rarity = $rarity;
	}

	/**
	 * A missing bound keeps vanilla's default.
	 */
	public function setPlayerDistance(?float $min, ?float $max) : void{
		$this->minPlayerDistance = $min ?? SpawnRuleGroup::DEFAULT_MIN_PLAYER_DISTANCE;
		$this->maxPlayerDistance = $max ?? SpawnRuleGroup::DEFAULT_MAX_PLAYER_DISTANCE;
	}

	/**
	 * A missing or negative limit is no limit, as in vanilla.
	 */
	public function setDensityLimit(?int $surface, ?int $underground) : void{
		$this->surfaceDensityLimit = $surface !== null && $surface >= 0 ? $surface : null;
		$this->caveDensityLimit = $underground !== null && $underground >= 0 ? $underground : null;
	}

	public function setHerd(int $min, int $max) : void{
		$this->herdMin = $min;
		$this->herdMax = $max;
	}

	/**
	 * @phpstan-param array<string, int> $permutations entity identifier => weight
	 */
	public function setPermutations(array $permutations) : void{
		$this->permutations = $permutations;
	}

	public function build() : ?SpawnRuleGroup{
		if($this->neverSpawns){
			return null;
		}
		if(count($this->habitatBands) === 0){
			return null; // every position is surface or underground
		}

		$conditions = $this->conditions;
		if(count($this->habitatBands) === 1){
			// A single marker pins the band; both markers allow any band.
			$conditions[] = new BandCondition(array_values($this->habitatBands)[0]);
		}

		return new SpawnRuleGroup(
			conditions: $conditions,
			weight: $this->weight,
			herdMin: $this->herdMin,
			herdMax: $this->herdMax,
			permutations: $this->permutations,
			requiredLiquid: $this->liquid,
			rarity: $this->rarity,
			minPlayerDistance: $this->minPlayerDistance,
			maxPlayerDistance: $this->maxPlayerDistance,
			surfaceDensityLimit: $this->surfaceDensityLimit,
			caveDensityLimit: $this->caveDensityLimit
		);
	}
}
