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

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\HabitatBandCondition;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use function array_values;
use function count;
use function uasort;

/**
 * Filled by the component parsers of one condition object, in any order: payloads are
 * last-write, conditions accumulate.
 */
final class SpawnRuleGroupBuilder{
	/** @phpstan-var list<SpawnCondition> */
	private array $conditions = [];

	private int $weight = 1;

	private int $herdMin = 1;

	private int $herdMax = 1;

	/** @phpstan-var array<string, int> */
	private array $permutations = [];

	/** @phpstan-var array<string, SpawnBand> */
	private array $habitatBands = [];

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
		$this->habitatBands[$band->name] = $band;
	}

	public function setWeight(int $weight) : void{
		$this->weight = $weight;
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

	public function build() : SpawnRuleGroup{
		$conditions = $this->conditions;
		if(count($this->habitatBands) === 1){
			// A single marker pins the band; both markers allow any band.
			$conditions[] = new HabitatBandCondition(array_values($this->habitatBands));
		}

		// Cheapest first; the stable sort keeps parse order for equal costs.
		uasort($conditions, static fn(SpawnCondition $a, SpawnCondition $b) : int => $a->getEvaluationCost() <=> $b->getEvaluationCost());

		return new SpawnRuleGroup(array_values($conditions), $this->weight, $this->herdMin, $this->herdMax, $this->permutations);
	}
}
