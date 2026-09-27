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

use IvanCraft623\MobPlugin\entity\MobCategory;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function count;
use function mt_rand;
use function strpos;
use function substr;

/**
 * Stage 3 — Apply. Materializes spawn requests on the main thread, re-validating live
 * state first (the world may have changed since the snapshot).
 */
final class SpawnApplier{
	/**
	 * Maximum horizontal jitter (blocks) between herd members and their lead position.
	 */
	private const HERD_SPREAD = 6;

	public function __construct(
		private SpawnRuleRegistry $registry,
		private WorldManager $worldManager,
		private SpawnCensus $census
	){}

	/**
	 * @phpstan-param list<SpawnRequest> $requests
	 */
	public function applyRequests(array $requests) : void{
		foreach($requests as $request){
			$world = $this->worldManager->getWorld($request->worldId);
			if($world === null){
				continue;
			}
			$entry = $this->registry->get($request->match->getIdentifier());
			if($entry === null){
				continue;
			}
			$group = $request->match->getGroup();
			$x = $request->x;
			$z = $request->z;
			if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
				continue;
			}
			$pos = new Vector3($x + 0.5, $request->y, $z + 0.5);
			if(!$this->hasRoomForMob($world, $pos)){
				continue; // blocks may have changed since the snapshot was taken
			}

			// permute_type: unimplemented targets fall back to the base entity.
			$spawnEntry = $entry;
			$permuteTarget = self::pickPermutation($group);
			if($permuteTarget !== null){
				$permuted = $this->registry->get($permuteTarget);
				if($permuted !== null){
					$spawnEntry = $permuted;
				}
			}

			$this->spawnHerd($world, $spawnEntry, $entry->getRules()->getCategory(), $group, $pos);
		}
	}

	private function spawnHerd(World $world, SpawnRuleBinding $entry, MobCategory $category, SpawnConditionGroup $group, Vector3 $pos) : void{
		// Live population cap re-check — other mobs may have spawned since collection.
		// The cap is checked once for the whole herd; spawning the full herd size can
		// overshoot the cap slightly (vanilla pack-spawn behavior) rather than splitting
		// progress, which is the documented approximation.
		$band = SpawnCensus::getSurfaceBand($world, $pos);
		$categoryCount = $this->census->countCategoryNearby($world, $category, $pos, $band);
		$cap = $category->getPopulationCaps()->get($band);
		if($categoryCount >= $cap){
			return;
		}

		$herd = $group->getHerd();
		$herdSize = $herd !== null ? mt_rand($herd->minSize, $herd->maxSize) : 1;
		// Herd spawn events are not applied — no consumer yet (see docs/spawning.md).

		$factory = $entry->getFactory();
		$habitatBand = $group->getHabitatBand(); // null = both bands allowed

		for($i = 0; $i < $herdSize; $i++){
			$memberPos = $i === 0 ? $pos : $this->jitterHerdPosition($world, $pos, $habitatBand);
			if($memberPos === null || !$this->isFarEnoughFromPlayers($world, $memberPos)){
				continue;
			}
			$entity = $factory($world, $memberPos, new SpawnConditionMatch($entry->getRules()->getIdentifier(), $group));
			$entity->spawnToAll();
		}
	}

	/**
	 * Positions a herd member near the lead. Surface-band members sit on top of their
	 * column; others keep the lead's depth. Null when there is no room.
	 */
	private function jitterHerdPosition(World $world, Vector3 $leadPos, ?SpawnBand $habitatBand) : ?Vector3{
		$x = (int) $leadPos->getX() + mt_rand(-self::HERD_SPREAD, self::HERD_SPREAD);
		$z = (int) $leadPos->getZ() + mt_rand(-self::HERD_SPREAD, self::HERD_SPREAD);
		if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
			return null;
		}
		if($habitatBand === SpawnBand::SURFACE){
			$y = ($world->getHighestBlockAt($x, $z) ?? $world->getMinY()) + 1;
		}else{
			$y = (int) $leadPos->getY();
		}
		$pos = new Vector3($x + 0.5, $y, $z + 0.5);
		if(!$this->hasRoomForMob($world, $pos)){
			return null;
		}

		return $pos;
	}

	/** Checked against every player in the world (jitter can drift toward others). */
	private function isFarEnoughFromPlayers(World $world, Vector3 $pos) : bool{
		foreach($world->getPlayers() as $player){
			if($player->getPosition()->distance($pos) < SpawnCollector::MIN_PLAYER_DISTANCE){
				return false;
			}
		}

		return true;
	}

	/** Feet/head passable, block under is a spawnable solid top (full cube, opaque). */
	private function hasRoomForMob(World $world, Vector3 $pos) : bool{
		$feet = $world->getBlock($pos);
		$head = $world->getBlock($pos->add(0, 1, 0));
		if($feet->isSolid() || $head->isSolid()){
			return false;
		}
		$ground = $world->getBlock($pos->add(0, -1, 0));

		return $ground->isFullCube() && !$ground->isTransparent();
	}

	/** Weighted pick from the group's permute_type payload; event suffixes are stripped. */
	private static function pickPermutation(SpawnConditionGroup $group) : ?string{
		$permutations = $group->getPermuteTypes();
		if(count($permutations) === 0){
			return null;
		}
		$total = 0;
		foreach($permutations as $permutation){
			$total += $permutation->weight;
		}
		if($total <= 0){
			return null; // no positive-weight permutation to pick — fall back to the base form
		}
		$roll = mt_rand(1, $total);
		foreach($permutations as $permutation){
			$roll -= $permutation->weight;
			if($roll <= 0){
				return $permutation->entityType === null ? null : self::stripEventSuffix($permutation->entityType);
			}
		}

		return $permutations[count($permutations) - 1]->entityType === null ? null : self::stripEventSuffix($permutations[count($permutations) - 1]->entityType);
	}

	/** "minecraft:pillager<minecraft:...>" → "minecraft:pillager". */
	private static function stripEventSuffix(?string $identifier) : ?string{
		if($identifier === null){
			return null;
		}
		$suffixStart = strpos($identifier, "<");

		return $suffixStart === false ? $identifier : substr($identifier, 0, $suffixStart);
	}
}
