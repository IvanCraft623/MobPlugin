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

use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

/**
 * Places a whole herd before any factory runs, so plugin code editing the world can't
 * invalidate positions computed from the pass's memos.
 */
final class HerdSpawner{
	/** Maximum horizontal offset (blocks) of herd members from the lead. */
	private const HERD_SPREAD = 6;

	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly Random $random
	){}

	/**
	 * The whole herd spawns even if it overshoots the cap slightly (vanilla pack spawning).
	 * The lead's position was checked by the pass, so it always spawns.
	 *
	 * @phpstan-param list<array{float, float, float}> $players x, y, z of every player in the world
	 */
	public function spawn(SpawnPlacement $placement, AttemptContext $lead, SpawnRules $rules, SpawnRuleGroup $group, array $players) : void{
		$herdSize = $this->random->nextRange($group->getHerdMin(), $group->getHerdMax());
		$members = [new Vector3($lead->getX() + 0.5, $lead->getY(), $lead->getZ() + 0.5)];
		for($i = 1; $i < $herdSize; $i++){
			$position = $this->getMemberPosition($placement, $group, $lead);
			if($position !== null && self::isFarEnoughFromPlayers($position, $players)){
				$members[] = $position;
			}
		}

		// permute_type targets without rules fall back to the base rules.
		$permutation = Utils::pickWeighted($this->random, $group->getPermutations());
		$spawnRules = $permutation !== null ? ($this->registry->get($permutation) ?? $rules) : $rules;
		$factory = $spawnRules->getFactory();
		$world = $placement->getWorld();
		foreach($members as $position){
			$factory($world, $position, $group)->spawnToAll();
		}
	}

	/**
	 * Aquatic members keep the lead's depth in the same liquid, surface land members stand
	 * on their own column's ground, and cave land members keep the lead's depth.
	 */
	private function getMemberPosition(SpawnPlacement $placement, SpawnRuleGroup $group, AttemptContext $lead) : ?Vector3{
		$x = $lead->getX() + $this->random->nextRange(-self::HERD_SPREAD, self::HERD_SPREAD);
		$z = $lead->getZ() + $this->random->nextRange(-self::HERD_SPREAD, self::HERD_SPREAD);
		if(!$placement->getWorld()->isChunkLoaded($x >> 4, $z >> 4)){
			return null;
		}
		$liquid = $group->getRequiredLiquid();
		$y = $liquid === SpawnLiquid::NONE && $lead->getBand() === SpawnBand::SURFACE
			? $placement->getGroundY($x, $z) + 1
			: $lead->getY();

		return $placement->hasRoom($x, $y, $z, $liquid) ? new Vector3($x + 0.5, $y, $z + 0.5) : null;
	}

	/**
	 * @phpstan-param list<array{float, float, float}> $players
	 */
	private static function isFarEnoughFromPlayers(Vector3 $position, array $players) : bool{
		foreach($players as [$x, $y, $z]){
			if(($x - $position->x) ** 2 + ($y - $position->y) ** 2 + ($z - $position->z) ** 2 < WorldSpawnPass::MIN_PLAYER_DISTANCE ** 2){
				return false;
			}
		}

		return true;
	}
}
