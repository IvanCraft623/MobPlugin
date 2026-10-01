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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\MobCategoryRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\utils\Random;

/**
 * Filter, then pick: every candidate under its category cap contributes its first
 * matching group, one is picked by group weight, then the cap roll decides. The
 * population is read only once some group has matched.
 */
final class SpawnSelector{
	public function __construct(
		private readonly Random $random,
		private readonly MobCategoryRegistry $categories
	){}

	/**
	 * @phpstan-param list<CandidateRule> $candidates
	 * @phpstan-return array{CandidateRule, SpawnRuleGroup}|null
	 */
	public function select(SpawnConditionContext $ctx, array $candidates) : ?array{
		$band = $ctx->getBand();
		/** @phpstan-var list<array{CandidateRule, SpawnRuleGroup, int, int}> $matches candidate, group, category count, cap */
		$matches = [];
		$weights = [];
		$population = null;
		foreach($candidates as $candidate){
			$category = $this->categories->get($candidate->getRules()->getCategoryId());
			if($category === null){
				continue;
			}
			$cap = $category->getCap($band);
			if($cap <= 0){
				continue; // full without counting
			}
			$group = $candidate->match($ctx);
			if($group === null || $group->getWeight() <= 0){
				continue;
			}
			$population ??= $ctx->getPopulation();
			$count = $population->getCategoryCount($category->id, $band);
			if($count >= $cap){
				continue;
			}
			$matches[] = [$candidate, $group, $count, $cap];
			$weights[] = $group->getWeight();
		}
		$index = Utils::pickWeighted($this->random, $weights);
		if($index === null){
			return null;
		}
		[$candidate, $group, $count, $cap] = $matches[$index];

		// The fuller the category's region, the likelier the attempt is dropped.
		if($this->random->nextFloat() * $cap >= $cap - $count){
			return null;
		}

		return [$candidate, $group];
	}
}
