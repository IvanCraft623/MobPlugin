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
 * group wins) plus the id of the population-control category it counts against.
 * Immutable plain data, shared across ticks and worlds. The id is resolved to a
 * MobCategory once, when the rule set is registered (SpawnRuleBinding::getCategory()).
 */
final class SpawnRules{
	/**
	 * @phpstan-param list<SpawnConditionGroup> $groups
	 */
	public function __construct(
		private readonly string $identifier,
		private readonly string $categoryId,
		private readonly array $groups
	){}

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
}
