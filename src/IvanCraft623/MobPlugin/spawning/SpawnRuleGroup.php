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

use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use function count;

final class SpawnRuleGroup{
	private readonly SpawnLiquid $requiredLiquid;

	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 * @phpstan-param array<string, int>    $permutations entity identifier => weight
	 */
	public function __construct(
		private readonly array $conditions,
		private readonly int $weight = 1,
		private readonly int $herdMin = 1,
		private readonly int $herdMax = 1,
		private readonly array $permutations = []
	){
		if($herdMin < 1 || $herdMax < $herdMin){
			throw new \InvalidArgumentException("Invalid herd size range [$herdMin, $herdMax]");
		}

		$requiredLiquid = SpawnLiquid::NONE;
		foreach($conditions as $condition){
			if($condition instanceof RangeCondition && $condition->getKind() === RangeCondition::KIND_LIQUID && $condition->getMin() !== null){
				$requiredLiquid = SpawnLiquid::from((int) $condition->getMin());
				break;
			}
		}
		$this->requiredLiquid = $requiredLiquid;
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

		return new self([...$this->conditions, ...$extra], $this->weight, $this->herdMin, $this->herdMax, $this->permutations);
	}

	public function matches(SpawnConditionContext $ctx) : bool{
		foreach($this->conditions as $condition){
			if(!$condition->test($ctx)){
				return false;
			}
		}

		return true;
	}
}
