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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;

/**
 * A mob's compiled spawn rule set: an ordered list of condition groups (first matching
 * group wins) bound to the mob's population-control category id. Immutable plain data,
 * shared across ticks and worlds. The category object is resolved from
 * MobCategoryRegistry by id at use time.
 */
final class SpawnRules{
	/** Weight used to pick this rule set; the widest group weight (computed once). */
	private readonly int $pickWeight;

	/**
	 * @phpstan-param list<SpawnConditionGroup> $groups
	 */
	public function __construct(
		private readonly string $identifier,
		private readonly string $categoryId,
		private readonly array $groups
	){
		$this->pickWeight = self::computePickWeight($groups);
	}

	public function getIdentifier() : string{
		return $this->identifier;
	}

	/**
	 * The population_control id this rule set counts against (the Bedrock string).
	 */
	public function getCategoryId() : string{
		return $this->categoryId;
	}

	/**
	 * @phpstan-return list<SpawnConditionGroup>
	 */
	public function getGroups() : array{
		return $this->groups;
	}

	/**
	 * Evaluates the rule set at the given position and returns the first matching group
	 * (vanilla Bedrock semantics: conditions are ordered alternatives), or null when no
	 * group matches.
	 */
	public function check(SpawnConditionContext $ctx) : ?SpawnConditionGroup{
		foreach($this->groups as $group){
			if($group->matches($ctx)){
				return $group;
			}
		}

		return null;
	}

	/**
	 * Weight used when picking this rule set for an attempt, before its conditions run
	 * (vanilla picks ONE mob per attempt; a failed pick is not retried). A rule set with
	 * several groups (e.g. the zombie's surface/underground split) has no single weight;
	 * the widest group weight is the closest single approximation. Computed once at
	 * construction — this is a hot-path read in the evaluator's weighted pick.
	 *
	 * @phpstan-param list<SpawnConditionGroup> $groups
	 */
	private static function computePickWeight(array $groups) : int{
		$weight = 1;
		foreach($groups as $group){
			$groupWeight = $group->getWeight();
			if($groupWeight > $weight){
				$weight = $groupWeight;
			}
		}

		return $weight;
	}

	/**
	 * @return int precomputed pick weight
	 */
	public function getPickWeight() : int{
		return $this->pickWeight;
	}
}
