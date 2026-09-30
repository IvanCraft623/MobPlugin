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

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\spawning\condition\DensityLimitCondition;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use function array_key_last;
use function min;

/**
 * Stage 3 — Apply. Materializes spawn requests on the main thread: re-validates the live
 * world (it may have changed since sampling), re-checks caps and density limits against
 * mobs spawned earlier in the same pass, then spawns the herd.
 */
final class SpawnApplier{
	/** Maximum horizontal offset (blocks) of herd members from the lead position. */
	private const HERD_SPREAD = 6;

	/**
	 * Player x/y/z per world, read once per apply pass.
	 *
	 * @phpstan-var array<int, list<array{float, float, float}>>
	 */
	private array $playerPositions = [];

	/**
	 * @param SpawnRuleRegistry $registry resolves permute_type targets
	 */
	public function __construct(
		private readonly SpawnRuleRegistry $registry,
		private readonly MobCategoryRegistry $categories,
		private readonly WorldManager $worldManager,
		private readonly Random $random
	){}

	/**
	 * @phpstan-param list<SpawnRequest> $requests
	 */
	public function apply(array $requests) : void{
		CustomTimings::$naturalSpawningApply->startTiming();
		try{
			$tally = new SpawnTally();
			foreach($requests as $request){
				$this->applyOne($request, $tally);
			}
		}finally{
			$this->playerPositions = [];
			CustomTimings::$naturalSpawningApply->stopTiming();
		}
	}

	private function applyOne(SpawnRequest $request, SpawnTally $tally) : void{
		$position = $request->position;
		$world = $this->worldManager->getWorld($position->worldId);
		if($world === null || !$world->isChunkLoaded($position->x >> 4, $position->z >> 4)){
			return;
		}
		$group = $request->group;
		if(!SpawnPlacement::hasRoom($world, $position->x, $position->y, $position->z, $group->getRequiredLiquid())){
			return; // blocks changed since sampling
		}

		$rules = $request->rules;
		$identifier = $rules->getIdentifier();
		$category = $this->categories->get($rules->getCategoryId());
		if($category === null){
			return;
		}
		$band = $position->band;
		$center = new Vector3($position->x + 0.5, $position->y, $position->z + 0.5);

		if($request->categoryCount + $tally->countCategory($position->worldId, $category->id, $band, $center) >= $category->getCap($band)){
			return;
		}
		$densityLimit = self::getDensityLimit($group, $band);
		if($densityLimit !== null && $request->densityCount + $tally->countIdentifier($position->worldId, $identifier, $band, $center) >= $densityLimit){
			return;
		}

		// permute_type: unregistered targets fall back to the base rules. The herd
		// still counts against the base rule's identifier and category, which is what the
		// density limit and cap above refer to.
		$spawnRules = $rules;
		$permuteTarget = $this->pickPermutation($group);
		if($permuteTarget !== null){
			$spawnRules = $this->registry->get($permuteTarget) ?? $rules;
		}

		foreach($this->spawnHerd($world, $spawnRules, $group, $position) as $spawned){
			$tally->record($position->worldId, $identifier, $category->id, $band, $spawned);
		}
	}

	/**
	 * Spawns one herd around the lead position and returns where members were spawned.
	 * The whole herd spawns even if it overshoots the cap slightly (vanilla pack
	 * spawning). Herd spawn events are not applied — no consumer yet.
	 *
	 * @phpstan-return list<Vector3>
	 */
	private function spawnHerd(World $world, SpawnRules $rules, SpawnRuleGroup $group, SpawnPosition $lead) : array{
		$herdSize = $this->random->nextRange($group->getHerdMin(), $group->getHerdMax());
		$factory = $rules->getFactory();

		$spawned = [];
		for($i = 0; $i < $herdSize; $i++){
			$memberPos = $i === 0
				? new Vector3($lead->x + 0.5, $lead->y, $lead->z + 0.5)
				: $this->herdMemberPosition($world, $group, $lead);
			if($memberPos === null || !$this->isFarEnoughFromPlayers($world, $memberPos)){
				continue;
			}
			$entity = $factory($world, $memberPos, $group);
			$entity->spawnToAll();
			$spawned[] = $memberPos;
		}

		return $spawned;
	}

	/**
	 * A herd member's position near the lead, or null when there is no room there.
	 * Aquatic members keep the lead's depth in the same liquid; land members of a surface
	 * lead stand on their own column's ground (terrain is uneven); land members of a cave
	 * lead keep its depth.
	 */
	private function herdMemberPosition(World $world, SpawnRuleGroup $group, SpawnPosition $lead) : ?Vector3{
		$x = $lead->x + $this->random->nextRange(-self::HERD_SPREAD, self::HERD_SPREAD);
		$z = $lead->z + $this->random->nextRange(-self::HERD_SPREAD, self::HERD_SPREAD);
		if(!$world->isChunkLoaded($x >> 4, $z >> 4)){
			return null;
		}
		$liquid = $group->getRequiredLiquid();
		$y = $liquid === SpawnLiquid::NONE && $lead->band === SpawnBand::SURFACE
			? SpawnPlacement::groundY($world, $x, $z) + 1
			: $lead->y;
		if(!SpawnPlacement::hasRoom($world, $x, $y, $z, $liquid)){
			return null;
		}

		return new Vector3($x + 0.5, $y, $z + 0.5);
	}

	/** Checked against every player in the world: members can drift toward others. */
	private function isFarEnoughFromPlayers(World $world, Vector3 $pos) : bool{
		$worldId = $world->getId();
		if(!isset($this->playerPositions[$worldId])){
			$positions = [];
			foreach($world->getPlayers() as $player){
				$playerPos = $player->getPosition();
				$positions[] = [$playerPos->x, $playerPos->y, $playerPos->z];
			}
			$this->playerPositions[$worldId] = $positions;
		}
		foreach($this->playerPositions[$worldId] as [$px, $py, $pz]){
			if(($px - $pos->x) ** 2 + ($py - $pos->y) ** 2 + ($pz - $pos->z) ** 2 < SpawnCollector::MIN_DISTANCE_SQUARED){
				return false;
			}
		}

		return true;
	}

	/** Weighted pick from the group's permute_type payload; null keeps the base type. */
	private function pickPermutation(SpawnRuleGroup $group) : ?string{
		$permutations = $group->getPermutations();
		$total = 0;
		foreach($permutations as $weight){
			$total += $weight;
		}
		if($total <= 0){
			return null;
		}
		$roll = $this->random->nextBoundedInt($total);
		foreach($permutations as $identifier => $weight){
			$roll -= $weight;
			if($roll < 0){
				return $identifier;
			}
		}

		return array_key_last($permutations);
	}

	/** The tightest density_limit the group sets for the band, or null when it sets none. */
	private static function getDensityLimit(SpawnRuleGroup $group, SpawnBand $band) : ?int{
		$limit = null;
		foreach($group->getConditions() as $condition){
			if($condition instanceof DensityLimitCondition){
				$bandLimit = $condition->getLimit($band);
				if($bandLimit !== null){
					$limit = $limit === null ? $bandLimit : min($limit, $bandLimit);
				}
			}
		}

		return $limit;
	}
}
