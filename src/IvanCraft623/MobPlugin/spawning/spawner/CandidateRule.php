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

namespace IvanCraft623\MobPlugin\spawning\spawner;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;

/**
 * A rule's groups that survived one cache key, each with the conditions the key could
 * not decide.
 */
final class CandidateRule{
	/**
	 * @phpstan-param list<array{SpawnRuleGroup, list<SpawnCondition>}> $groups in rule order
	 */
	public function __construct(
		private readonly SpawnRules $rules,
		private readonly array $groups
	){}

	public function getRules() : SpawnRules{
		return $this->rules;
	}

	/**
	 * @phpstan-return list<SpawnRuleGroup> every matching group, in rule order
	 */
	public function match(SpawnConditionContext $ctx) : array{
		$matches = [];
		$distance = $ctx->getNearestPlayerDistance();
		foreach($this->groups as [$group, $residuals]){
			if(!$group->admitsPlayerDistance($distance)){
				continue;
			}
			foreach($residuals as $condition){
				if(!$condition->test($ctx)){
					continue 2;
				}
			}
			$matches[] = $group;
		}

		return $matches;
	}
}