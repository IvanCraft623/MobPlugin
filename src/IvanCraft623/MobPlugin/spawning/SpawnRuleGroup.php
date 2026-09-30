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
	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 * @phpstan-param array<string, int>   $permutations   entity identifier => weight
	 * @phpstan-param SpawnLiquid          $requiredLiquid the liquid the feet must be in; NONE for land mobs
	 */
	public function __construct(
		private readonly array $conditions,
		private readonly int $weight = 1,
		private readonly int $herdMin = 1,
		private readonly int $herdMax = 1,
		private readonly array $permutations = [],
		private readonly SpawnLiquid $requiredLiquid = SpawnLiquid::NONE
	){
		if($herdMin < 1 || $herdMax < $herdMin){
			throw new \InvalidArgumentException("Invalid herd size range [$herdMin, $herdMax]");
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

	/**
	 * @phpstan-param list<SpawnCondition> $extra
	 */
	public function withConditions(array $extra) : self{
		if(count($extra) === 0){
			return $this;
		}

		return new self([...$this->conditions, ...$extra], $this->weight, $this->herdMin, $this->herdMax, $this->permutations, $this->requiredLiquid);
	}

	/**
	 * Aquatic groups spawn only in their liquid, and land groups (which carry no "not in
	 * liquid" condition) only out of any liquid.
	 */
	public function admitsLiquid(SpawnLiquid $feetLiquid) : bool{
		return $feetLiquid === $this->requiredLiquid;
	}

	public function matches(SpawnConditionContext $ctx) : bool{
		if(!$this->admitsLiquid($ctx->getFeetLiquid())){
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
