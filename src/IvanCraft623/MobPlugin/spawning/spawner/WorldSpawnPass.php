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

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use pocketmine\block\utils\SupportType;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\World;
use function array_flip;
use function count;
use function min;
use function sqrt;
use const PHP_FLOAT_MAX;

/**
 * One world's spawning for one tick. Each attempt runs from sampling to spawning before
 * the next one starts; every location-keyed memo lives only as long as the pass.
 */
final class WorldSpawnPass{
	/** Up to this tick radius vanilla clamps every rule's maximum player distance to 44. */
	private const CLAMPED_TICK_RADIUS = 4;
	private const CLAMPED_PLAYER_DISTANCE = 44;

	private readonly SpawnPlacement $placement;

	private readonly PopulationCensus $census;

	/** @phpstan-var list<array{float, float, float}> */
	private readonly array $players;

	private readonly int $difficulty;

	private readonly int $time;

	private readonly bool $clampDistance;

	/** @phpstan-var array<int, int>|null chunk hash => index, read on first use */
	private ?array $tickingChunks = null;

	public function __construct(
		private readonly World $world,
		private readonly CandidateCache $candidateCache,
		private readonly SpawnSelector $selector,
		private readonly HerdSpawner $herdSpawner,
		SpawnRuleRegistry $registry,
		private readonly Random $random
	){
		$this->placement = new SpawnPlacement($world);
		$this->census = new PopulationCensus($this->placement, $registry, CustomTimings::$naturalSpawningCensus);
		$players = [];
		foreach($world->getPlayers() as $player){
			$pos = $player->getPosition();
			$players[] = [$pos->x, $pos->y, $pos->z];
		}
		$this->players = $players;
		$this->difficulty = $world->getDifficulty();
		$this->time = $world->getTime();
		$this->clampDistance = $world->getChunkTickRadius() <= self::CLAMPED_TICK_RADIUS;
	}

	/**
	 * One random column of a ticking chunk: the surface position on its ground, then
	 * every cave position below it down to the world bottom, as vanilla does.
	 */
	public function attempt(int $chunkX, int $chunkZ) : void{
		// Past the clamped radius vanilla instead needs the chunks around to be ticking.
		if(!$this->clampDistance && !$this->isSurroundedByTickingChunks($chunkX, $chunkZ)){
			return;
		}
		CustomTimings::$naturalSpawningSample->startTiming();
		try{
			$this->sampleColumn($chunkX, $chunkZ);
		}finally{
			CustomTimings::$naturalSpawningSample->stopTiming();
		}
	}

	private function isSurroundedByTickingChunks(int $chunkX, int $chunkZ) : bool{
		$this->tickingChunks ??= array_flip($this->world->getTickingChunks());
		for($x = $chunkX - 1; $x <= $chunkX + 1; $x++){
			for($z = $chunkZ - 1; $z <= $chunkZ + 1; $z++){
				if(!isset($this->tickingChunks[World::chunkHash($x, $z)])){
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Drops the ground and population memos. Called whenever code we don't control (a
	 * factory) may have changed the world.
	 */
	private function invalidateWorldMemos() : void{
		$this->placement->clear();
		$this->census->clear();
	}

	private function sampleColumn(int $chunkX, int $chunkZ) : void{
		$x = ($chunkX << 4) + $this->random->nextBoundedInt(16);
		$z = ($chunkZ << 4) + $this->random->nextBoundedInt(16);

		$groundY = $this->placement->getGroundY($x, $z);
		if($groundY + 2 < $this->world->getMaxY()){
			$this->tryPosition($x, $groundY + 1, $z, $groundY);
		}

		// Strictly below the ground, so genuinely underground. The scan doesn't stop when a
		// herd spawns.
		$minY = $this->world->getMinY();
		for($y = $groundY - 1; $y > $minY; $y--){
			$this->tryPosition($x, $y, $z, $groundY);
		}
	}

	/**
	 * Feet and head in blocks with no collision boxes, over a block with a full top surface.
	 * Blocks stay out of the world's block cache: a column scan reads far more of them than
	 * anything else will reuse.
	 */
	private function tryPosition(int $x, int $y, int $z, int $groundY) : void{
		// Feet first: most of a column is rock, which costs this one read.
		$feet = $this->world->getBlockAt($x, $y, $z, addToCache: false);
		if(count($feet->getCollisionBoxes()) !== 0 || count($this->world->getBlockAt($x, $y + 1, $z, addToCache: false)->getCollisionBoxes()) !== 0){
			return;
		}
		$below = $this->world->getBlockAt($x, $y - 1, $z, addToCache: false);
		if($below->getSupportType(Facing::UP) !== SupportType::FULL){
			return;
		}
		// From the block's own coordinates, as vanilla measures it.
		$nearestSquared = PHP_FLOAT_MAX;
		foreach($this->players as [$px, $py, $pz]){
			$nearestSquared = min($nearestSquared, ($px - $x) ** 2 + ($py - $y) ** 2 + ($pz - $z) ** 2);
		}
		if($this->clampDistance && $nearestSquared > self::CLAMPED_PLAYER_DISTANCE ** 2){
			return;
		}

		$ctx = new AttemptContext(
			$this->world,
			$this->census,
			$x,
			$y,
			$z,
			$groundY,
			SpawnBand::fromPosition($y, $groundY),
			$this->world->getBiomeId($x, $y, $z),
			SpawnLiquid::fromBlockTypeId($feet->getTypeId()),
			$below->getTypeId(),
			$this->difficulty,
			sqrt($nearestSquared),
			$this->time
		);
		$candidates = $this->candidateCache->getCandidates($ctx);
		if($candidates === []){
			return;
		}

		// Sample stays paused while the rest of the attempt runs under its own timings.
		CustomTimings::$naturalSpawningSample->stopTiming();
		try{
			$this->selectAndSpawn($ctx, $candidates);
		}finally{
			CustomTimings::$naturalSpawningSample->startTiming();
		}
	}

	/**
	 * @phpstan-param non-empty-list<CandidateRule> $candidates
	 */
	private function selectAndSpawn(AttemptContext $ctx, array $candidates) : void{
		CustomTimings::$naturalSpawningSelect->startTiming();
		try{
			$selected = $this->selector->select($ctx, $candidates);
		}finally{
			CustomTimings::$naturalSpawningSelect->stopTiming();
		}
		if($selected === null){
			return;
		}

		[$candidate, $group, $room] = $selected;
		CustomTimings::$naturalSpawningSpawn->startTiming();
		try{
			$this->herdSpawner->spawn($this->world, $ctx, $candidate->getRules(), $group, $room);
			$this->invalidateWorldMemos();
		}finally{
			CustomTimings::$naturalSpawningSpawn->stopTiming();
		}
	}
}
