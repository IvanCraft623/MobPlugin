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
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\utils\Random;
use function min;

/**
 * Filter, then pick: every candidate under its category cap contributes its matching
 * groups that are under their density limit, one is picked by group weight, then its
 * rarity roll decides. The pick comes with the room left for its herd under both. The
 * population is read only once some group has matched.
 */
final class SpawnSelector{
	public function __construct(
		private readonly Random $random,
		private readonly MobCategoryRegistry $categories
	){}

	/**
	 * @phpstan-param list<CandidateRule> $candidates
	 */
	public function select(SpawnConditionContext $ctx, array $candidates) : ?SpawnSelection{
		$band = $ctx->getBand();
		/** @phpstan-var list<SpawnSelection> $matches */
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
			$groups = $candidate->match($ctx);
			if($groups === []){
				continue;
			}
			if(!$ctx->hasRoomFor($candidate->getRules()->getSize())){
				continue;
			}
			$population ??= $ctx->getPopulation();
			$room = $cap - $population->getCategoryCount($category->id, $band);
			if($room <= 0){
				continue;
			}
			$identifier = $candidate->getRules()->getIdentifier();
			foreach($groups as $group){
				if($group->getWeight() <= 0){
					continue;
				}
				$groupRoom = $room;
				$densityLimit = $group->getDensityLimit($band);
				if($densityLimit !== null){
					$groupRoom = min($room, $densityLimit - $population->getIdentifierCount($identifier, $band));
					if($groupRoom <= 0){
						continue;
					}
				}
				$matches[] = new SpawnSelection($candidate->getRules(), $group, $groupRoom);
				$weights[] = $group->getWeight();
			}
		}
		$index = Utils::pickWeighted($this->random, $weights);
		if($index === null){
			return null;
		}

		// Rolled after the pick: losing it wastes the attempt, as in vanilla.
		$rarity = $matches[$index]->group->getRarity();
		if($rarity > 0 && $this->random->nextBoundedInt($rarity) !== 0){
			return null;
		}

		return $matches[$index];
	}
}
