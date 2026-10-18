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

use IvanCraft623\MobPlugin\event\MobSpawnCause;
use IvanCraft623\MobPlugin\event\MobSpawnEvent;
use IvanCraft623\MobPlugin\spawning\event\SpawnEventContext;
use IvanCraft623\MobPlugin\spawning\event\SpawnEventRegistry;
use IvanCraft623\MobPlugin\spawning\population\EntitySpawnBands;
use IvanCraft623\MobPlugin\spawning\SpawnPermutation;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use function array_map;
use function count;
use function min;
use function round;

/**
 * Spawns every member on the lead's block, as vanilla does.
 */
final class HerdSpawner{
	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly Random $random,
		private readonly EntitySpawnBands $spawnBands
	){}

	/**
	 * Builds every member first, so the events can see the whole herd, and spawns them last.
	 *
	 * @phpstan-return list<Entity> the members that spawned
	 */
	public function spawn(AttemptContext $lead, SpawnSelection $selection) : array{
		$rules = $selection->rules;
		$group = $selection->group;
		$herds = $group->getHerds();
		$herd = $herds[$this->random->nextBoundedInt(count($herds))];
		$herdMin = $herd->getMin();
		$herdSize = min($selection->room, $herdMin + (int) round($this->random->nextFloat() ** 2 * ($herd->getMax() - $herdMin)));
		$band = $lead->getBand();
		$world = $lead->getWorld();
		$position = new Vector3($lead->getX() + 0.5, $lead->getY(), $lead->getZ() + 0.5);
		$weights = array_map(static fn(SpawnPermutation $permutation) : int => $permutation->getWeight(), $group->getPermutations());
		$events = SpawnEventRegistry::getInstance();
		$members = [];

		for($i = 0; $i < $herdSize; $i++){
			// permute_type targets without rules fall back to the base rules.
			$permutation = Utils::pickWeighted($this->random, $weights);
			$permutation = $permutation !== null ? $group->getPermutations()[$permutation] : null;
			$spawnRules = $permutation !== null ? ($this->registry->get($permutation->getIdentifier()) ?? $rules) : $rules;

			$member = ($spawnRules->getFactory())($world, $position, $group);
			$members[] = $member;
			$permutationEvent = $permutation?->getEvent();
			if($permutationEvent !== null){
				$events->fire($permutationEvent, $member, new SpawnEventContext($members, $i, $group));
			}
		}

		$initialEvent = $herd->getInitialEvent();
		$event = $herd->getEvent();
		foreach($members as $i => $member){
			$ctx = new SpawnEventContext($members, $i, $group);
			if($initialEvent !== null && $i < $herd->getInitialEventCount()){
				$events->fire($initialEvent, $member, $ctx);
			}
			if($event !== null && $i >= $herd->getEventSkipCount()){
				$events->fire($event, $member, $ctx);
			}
		}

		foreach($members as $member){
			$this->spawnBands->set($member, $band);
			(new MobSpawnEvent($member, MobSpawnCause::NATURAL))->call();
			$member->spawnToAll();
		}

		return $members;
	}
}
