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

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\World;
use function min;
use function round;

/**
 * Spawns every member on the lead's block, as vanilla does.
 */
final class HerdSpawner{

	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly Random $random
	){}

	/**
	 * @param int $room mobs the category can still take, at least 1
	 */
	public function spawn(World $world, AttemptContext $lead, SpawnRules $rules, SpawnRuleGroup $group, int $room) : void{
		$herdMin = $group->getHerdMin();
		$herdSize = min($room, $herdMin + (int) round($this->random->nextFloat() ** 2 * ($group->getHerdMax() - $herdMin)));
		$onSurface = $lead->getBand() === SpawnBand::SURFACE;

		for($i = 0; $i < $herdSize; $i++){
			// permute_type targets without rules fall back to the base rules.
			$permutation = Utils::pickWeighted($this->random, $group->getPermutations());
			$spawnRules = $permutation !== null ? ($this->registry->get($permutation) ?? $rules) : $rules;

			$entity = ($spawnRules->getFactory())($world, new Vector3($lead->getX() + 0.5, $lead->getY(), $lead->getZ() + 0.5), $group);
			if($entity instanceof Mob){
				$entity->setSpawnedOnSurface($onSurface);
			}
			$entity->spawnToAll();
		}
	}
}
