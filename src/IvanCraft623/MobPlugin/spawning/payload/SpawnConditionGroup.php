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

namespace IvanCraft623\MobPlugin\spawning\payload;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\DensityLimitCondition;
use IvanCraft623\MobPlugin\spawning\plan\LiquidConstrained;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use function count;
use function min;

/**
 * One alternative of a spawn rule set: the AND list of its conditions plus the payload
 * a match carries (weight, herd, permute_type, spawn_event). A rule set's groups are
 * ordered alternatives — the first fully passing group wins. Immutable plain data.
 */
final class SpawnConditionGroup{
	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 * @phpstan-param list<PermuteType> $permuteTypes
	 */
	public function __construct(
		private readonly array $conditions,
		private readonly int $weight = 1,
		private readonly ?Herd $herd = null,
		private readonly array $permuteTypes = [],
		private readonly ?SpawnEvent $event = null,
		/** Habitat band herd members should occupy, when the conditions pin one band. */
		private readonly ?SpawnBand $habitatBand = null
	){}

	/**
	 * @phpstan-return list<SpawnCondition>
	 */
	public function getConditions() : array{
		return $this->conditions;
	}

	public function getWeight() : int{
		return $this->weight;
	}

	public function getHerd() : ?Herd{
		return $this->herd;
	}

	/**
	 * @phpstan-return list<PermuteType>
	 */
	public function getPermuteTypes() : array{
		return $this->permuteTypes;
	}

	public function getEvent() : ?SpawnEvent{
		return $this->event;
	}

	public function getHabitatBand() : ?SpawnBand{
		return $this->habitatBand;
	}

	/**
	 * The tightest density_limit this group sets for the given band (the maximum nearby
	 * same-identifier count a spawn may start from), or null when it sets none. The
	 * applier re-checks it so repeated herds in one pass can't slip past the limit.
	 */
	public function densityLimitFor(SpawnBand $band) : ?int{
		$limit = null;
		foreach($this->conditions as $condition){
			if($condition instanceof DensityLimitCondition){
				$candidateLimit = $condition->limitFor($band);
				if($candidateLimit !== null){
					$limit = $limit === null ? $candidateLimit : min($limit, $candidateLimit);
				}
			}
		}

		return $limit;
	}

	/**
	 * The liquid (BlockTypeIds::WATER/LAVA) this group requires at the feet, or null for
	 * land spawns. Drives the applier's placement checks for the lead and herd members.
	 */
	public function getRequiredLiquid() : ?int{
		foreach($this->conditions as $condition){
			if($condition instanceof LiquidConstrained){
				return $condition->getRequiredLiquidTypeId();
			}
		}

		return null;
	}

	/**
	 * Returns a copy of this group with one extra condition ANDed onto its list, carrying
	 * the full payload (weight, herd, permute types, event, habitat band) through.
	 *
	 * @phpstan-param list<SpawnCondition> $extra conditions to append
	 */
	public function withConditions(array $extra) : self{
		if(count($extra) === 0){
			return $this;
		}

		return new self(
			[...$this->conditions, ...$extra],
			$this->weight,
			$this->herd,
			$this->permuteTypes,
			$this->event,
			$this->habitatBand
		);
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
