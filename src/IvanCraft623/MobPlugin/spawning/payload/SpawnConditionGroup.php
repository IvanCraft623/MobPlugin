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
use IvanCraft623\MobPlugin\spawning\SpawnBand;

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

	public function matches(SpawnConditionContext $ctx) : bool{
		foreach($this->conditions as $condition){
			if(!$condition->test($ctx)){
				return false;
			}
		}

		return true;
	}
}
