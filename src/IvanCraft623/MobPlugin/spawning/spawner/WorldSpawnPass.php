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
use IvanCraft623\MobPlugin\spawning\population\MobPopulation;
use IvanCraft623\MobPlugin\spawning\population\PopulationCensus;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use pocketmine\block\Block;
use pocketmine\block\utils\SupportType;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\World;
use function array_flip;
use function ceil;
use function count;
use function floor;
use function max;
use function min;
use function sqrt;
use const INF;
use const PHP_FLOAT_MAX;

/**
 * One world's spawning for one tick. Each attempt runs from sampling to spawning before
 * the next one starts; every location-keyed memo lives only as long as the pass.
 */
final class WorldSpawnPass{
	/** At a tick radius this low, vanilla spawns nothing farther than this from a player. */
	private const LOW_TICK_RADIUS = 4;
	private const LOW_TICK_RADIUS_MAX_PLAYER_DISTANCE = 44;

	private readonly GroundLevelCache $groundLevels;

	private readonly PopulationCensus $census;

	private readonly int $difficulty;

	private readonly int $time;

	private readonly bool $lowTickRadius;

	/** No position farther than this from every player can spawn anything; INF for no limit. */
	private readonly float $reach;

	/** @phpstan-var array<int, int>|null chunk hash => index, built on first use */
	private ?array $tickingChunkIndex = null;

	/**
	 * @phpstan-param list<int>                                $tickingChunks     the world's ticking chunk hashes this tick
	 * @phpstan-param non-empty-list<array{float, float, float}> $players           the positions of the players that allow spawns
	 * @phpstan-param float                                    $maxPlayerDistance the largest maximum player distance of any group; INF for no limit
	 */
	public function __construct(
		private readonly World $world,
		private readonly array $tickingChunks,
		private readonly array $players,
		private readonly CandidateCache $candidateCache,
		private readonly SpawnSelector $selector,
		private readonly HerdSpawner $herdSpawner,
		MobPopulation $population,
		SpawnRuleRegistry $registry,
		private readonly Random $random,
		float $maxPlayerDistance
	){
		$this->groundLevels = new GroundLevelCache($world);
		$this->census = $population->createCensus($world, $this->groundLevels, $registry, CustomTimings::$naturalSpawningCensus);
		$this->difficulty = $world->getDifficulty();
		$this->time = $world->getTime();
		$this->lowTickRadius = $world->getChunkTickRadius() <= self::LOW_TICK_RADIUS;
		$this->reach = $this->lowTickRadius ? min(self::LOW_TICK_RADIUS_MAX_PLAYER_DISTANCE, $maxPlayerDistance) : $maxPlayerDistance;
	}

	/**
	 * Whether the chunk may be attempted at all: above the low radius vanilla needs the
	 * chunks around it to be ticking too.
	 */
	public function canAttempt(int $chunkX, int $chunkZ) : bool{
		return $this->lowTickRadius || $this->isSurroundedByTickingChunks($chunkX, $chunkZ);
	}

	/**
	 * One random column of a ticking chunk: the surface position on its ground, then
	 * every cave position below it down to the world bottom, as vanilla does.
	 */
	public function attempt(int $chunkX, int $chunkZ) : void{
		CustomTimings::$naturalSpawningSample->startTiming();
		try{
			$this->sampleColumn($chunkX, $chunkZ);
		}finally{
			CustomTimings::$naturalSpawningSample->stopTiming();
		}
	}

	private function isSurroundedByTickingChunks(int $chunkX, int $chunkZ) : bool{
		$this->tickingChunkIndex ??= array_flip($this->tickingChunks);
		for($x = $chunkX - 1; $x <= $chunkX + 1; $x++){
			for($z = $chunkZ - 1; $z <= $chunkZ + 1; $z++){
				if(!isset($this->tickingChunkIndex[World::chunkHash($x, $z)])){
					return false;
				}
			}
		}

		return true;
	}

	private function sampleColumn(int $chunkX, int $chunkZ) : void{
		$x = ($chunkX << 4) + $this->random->nextBoundedInt(16);
		$z = ($chunkZ << 4) + $this->random->nextBoundedInt(16);

		// Only the players in horizontal reach matter to this column, and they bound the Y
		// range worth scanning.
		$reachSquared = $this->reach ** 2;
		$nearby = [];
		$lowestY = -INF;
		$highestY = INF;
		if($this->reach !== INF){
			$lowestY = INF;
			$highestY = -INF;
			foreach($this->players as [$px, $py, $pz]){
				$horizontalSquared = ($px - $x) ** 2 + ($pz - $z) ** 2;
				if($horizontalSquared > $reachSquared){
					continue;
				}
				$nearby[] = [$horizontalSquared, $py];
				$verticalReach = sqrt($reachSquared - $horizontalSquared);
				$lowestY = min($lowestY, $py - $verticalReach);
				$highestY = max($highestY, $py + $verticalReach);
			}
			if(count($nearby) === 0){
				return;
			}
		}else{
			foreach($this->players as [$px, $py, $pz]){
				$nearby[] = [($px - $x) ** 2 + ($pz - $z) ** 2, $py];
			}
		}

		$groundY = $this->groundLevels->getGroundY($x, $z);
		$surfaceY = $groundY + 1;
		if($surfaceY < $this->world->getMaxY() && $surfaceY >= $lowestY && $surfaceY <= $highestY){
			$this->tryPosition($x, $surfaceY, $z, SpawnBand::SURFACE, $nearby);
		}

		// Strictly below the ground, so genuinely underground. The scan doesn't stop when a
		// herd spawns.
		$bottomY = (int) max($this->world->getMinY() + 1, ceil($lowestY));
		// The block under one position is the feet of the next.
		$feet = null;
		for($y = (int) min($groundY - 1, floor($highestY)); $y >= $bottomY; $y--){
			$feet = $this->tryPosition($x, $y, $z, SpawnBand::CAVE, $nearby, $feet);
		}
	}

	/**
	 * Whether a mob can stand in the block: nothing in it to collide with. A flat collision
	 * box doesn't count, which is how PocketMine models a single snow layer.
	 */
	public static function isPassable(Block $block) : bool{
		foreach($block->getCollisionBoxes() as $box){
			if($box->maxY > $box->minY){
				return false;
			}
		}

		return true;
	}

	/**
	 * Feet in a passable block, over a block with a full top surface. Blocks stay out of
	 * the world's block cache: a column scan reads far more than anything will reuse.
	 *
	 * @phpstan-param list<array{float, float}> $nearby squared horizontal distance and Y of the players in reach
	 * @phpstan-param Block|null                $feet   the block at the position, if already read
	 *
	 * @return Block|null the block below, if it was read and is still current
	 */
	private function tryPosition(int $x, int $y, int $z, SpawnBand $band, array $nearby, ?Block $feet = null) : ?Block{
		// Feet first: most of a column is rock, which costs this one read.
		$feet ??= $this->world->getBlockAt($x, $y, $z, addToCache: false);
		if(!self::isPassable($feet)){
			return null;
		}
		$below = $this->world->getBlockAt($x, $y - 1, $z, addToCache: false);
		if($below->getSupportType(Facing::UP) !== SupportType::FULL){
			return $below;
		}
		// From the block's own coordinates, as vanilla measures it.
		$nearestSquared = PHP_FLOAT_MAX;
		foreach($nearby as [$horizontalSquared, $py]){
			$nearestSquared = min($nearestSquared, $horizontalSquared + ($py - $y) ** 2);
		}
		if($nearestSquared > $this->reach ** 2){
			return $below;
		}

		$ctx = new AttemptContext(
			$this->world,
			$this->census,
			$x,
			$y,
			$z,
			$band,
			$this->world->getBiomeId($x, $y, $z),
			SpawnLiquid::fromBlockTypeId($feet->getTypeId()),
			$below,
			$this->difficulty,
			sqrt($nearestSquared),
			$this->time,
			$this->random
		);
		$candidates = $this->candidateCache->getCandidates($ctx);
		if(count($candidates) === 0){
			return $below;
		}

		// Sample stays paused while the rest of the attempt runs under its own timings.
		CustomTimings::$naturalSpawningSample->stopTiming();
		try{
			$this->selectAndSpawn($ctx, $candidates);
		}finally{
			CustomTimings::$naturalSpawningSample->startTiming();
		}

		// A factory may have changed blocks.
		return null;
	}

	/**
	 * @phpstan-param non-empty-list<CandidateRule> $candidates
	 */
	private function selectAndSpawn(AttemptContext $ctx, array $candidates) : void{
		CustomTimings::$naturalSpawningSelect->startTiming();
		try{
			$selection = $this->selector->select($ctx, $candidates);
		}finally{
			CustomTimings::$naturalSpawningSelect->stopTiming();
		}
		if($selection === null){
			return;
		}

		CustomTimings::$naturalSpawningSpawn->startTiming();
		try{
			foreach($this->herdSpawner->spawn($ctx, $selection) as $entity){
				$this->census->add($entity);
			}
			// A factory may have changed blocks.
			$this->groundLevels->clear();
		}finally{
			CustomTimings::$naturalSpawningSpawn->stopTiming();
		}
	}
}
