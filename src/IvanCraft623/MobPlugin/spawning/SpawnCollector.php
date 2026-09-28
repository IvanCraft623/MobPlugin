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
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use function acos;
use function cos;
use function count;
use function max;
use function mt_getrandmax;
use function mt_rand;
use function sin;

use const M_PI;

/**
 * Stage 1 — Collect. Picks random candidate positions in the spawn shell around the
 * world's players and snapshots the world state the conditions need.
 */
final class SpawnCollector{
	/** Mobs never spawn closer than this to a player (vanilla despawns them immediately). */
	public const MIN_PLAYER_DISTANCE = 24;

	/** Outer spawn-shell radius, matching the wiki's simulation-distance-4 shell (24-44). */
	public const MAX_PLAYER_DISTANCE = 44;

	/** Cave herd attempts per column (vanilla scans every spawnable block; we sample). */
	public const CAVE_ATTEMPTS_PER_COLUMN = 2;

	public function __construct(
		private SpawnCensus $census
	){}

	/**
	 * One per-world collection pass; the caller gates on players online + non-peaceful.
	 *
	 * @phpstan-param list<Player> $players
	 *
	 * @phpstan-return list<SpawnCandidateSnapshot>
	 */
	public function collect(World $world, array $players, int $attempts) : array{
		$snapshots = [];
		for($attempt = 0; $attempt < $attempts; $attempt++){
			foreach($this->collectChunkCandidates($world, $players) as $snapshot){
				$snapshots[] = $snapshot;
			}
		}

		// One census pass for the whole batch, not one per candidate (hot path).
		$density = [];
		$population = [];
		$centers = [];
		foreach($snapshots as $snapshot){
			$centers[] = new Vector3($snapshot->x, $snapshot->y, $snapshot->z);
		}
		CustomTimings::$naturalSpawningCensus->startTiming();
		try{
			$this->census->countForBatch($world, $centers, $density, $population);
		}finally{
			CustomTimings::$naturalSpawningCensus->stopTiming();
		}
		foreach($snapshots as $i => $snapshot){
			$snapshot->densityCounts = $density[$i];
			$snapshot->populationCounts = $population[$i];
		}

		return $snapshots;
	}

	/**
	 * One chunk evaluation: random chunk + column within the shell, then a surface herd
	 * attempt at the first spawnable block from the top and cave herd attempts at random
	 * depths.
	 *
	 * @phpstan-param list<Player> $players
	 *
	 * @phpstan-return list<SpawnCandidateSnapshot>
	 */
	private function collectChunkCandidates(World $world, array $players) : array{
		$player = $players[mt_rand(0, count($players) - 1)];
		$playerPos = $player->getPosition();
		// Spherical shell 24-44 blocks from the player.
		$distance = self::MIN_PLAYER_DISTANCE + (self::MAX_PLAYER_DISTANCE - self::MIN_PLAYER_DISTANCE) * mt_rand() / mt_getrandmax();
		$theta = mt_rand(0, 359) * M_PI / 180.0;
		$phi = acos(2.0 * mt_rand() / mt_getrandmax() - 1.0);
		$centerX = $playerPos->getX() + sin($phi) * cos($theta) * $distance;
		$centerZ = $playerPos->getZ() + sin($phi) * sin($theta) * $distance;
		$chunkX = ((int) $centerX) >> 4;
		$chunkZ = ((int) $centerZ) >> 4;

		$chunk = $world->isChunkLoaded($chunkX, $chunkZ) ? $world->getChunk($chunkX, $chunkZ) : null;
		if($chunk === null || $chunk->isLightPopulated() !== true){
			return []; // light not calculated yet — getFullLightAt() would wrongly read dark
		}
		$x = ($chunkX << 4) + mt_rand(0, 15);
		$z = ($chunkZ << 4) + mt_rand(0, 15);
		$minY = $world->getMinY();

		$snapshots = [];
		// Surface attempt: the column's ground is the highest *passable* platform — a
		// solid, full-cube, opaque block — scanning down from the top. Air and unpassable
		// solids (leaf/stair/glass) are skipped, so the herd sits on the true ground even
		// under a forest canopy. The mob stands in the air cell above it (y = platform + 1).
		$topY = $world->getHighestBlockAt($x, $z) ?? $minY;
		$surfaceY = null;
		for($y = $topY; $y >= $minY; $y--){
			$block = $world->getBlockAt($x, $y, $z);
			if(!$block->isSolid()){
				continue;
			}
			if($block->isFullCube() && !$block->isTransparent()){
				$surfaceY = $y;
				break;
			}
		}
		// The true ground the bands and the cave range reference (column top when no
		// passable platform was found).
		$surfaceRef = $surfaceY ?? $topY;
		if($surfaceY !== null && $surfaceY + 1 <= $world->getMaxY()){
			$surface = $this->snapshotCandidate($world, $players, $x, $surfaceY + 1, $z, $surfaceRef);
			if($surface !== null){
				$snapshots[] = $surface;
			}
		}

		// Cave attempts: strictly below the ground, so they are genuinely underground —
		// never thin surface pockets right under a leafy canopy. `surfaceRef` also labels
		// them CAVE for the population caps.
		$caveMax = max($minY, $surfaceRef - 1);
		if($caveMax > $minY){
			for($caveAttempt = 0; $caveAttempt < self::CAVE_ATTEMPTS_PER_COLUMN; $caveAttempt++){
				$y = mt_rand($minY + 1, $caveMax);
				$feet = $world->getBlockAt($x, $y, $z);
				$ground = $world->getBlockAt($x, $y - 1, $z);
				if(!$feet->isSolid() && $ground->isFullCube() && !$ground->isTransparent()){
					$snapshot = $this->snapshotCandidate($world, $players, $x, $y, $z, $surfaceRef);
					if($snapshot !== null){
						$snapshots[] = $snapshot;
					}
				}
			}
		}

		return $snapshots;
	}

	/**
	 * Snapshots the world state one candidate position needs, including the band-split
	 * censuses for the caps.
	 *
	 * @phpstan-param list<Player> $players
	 */
	private function snapshotCandidate(World $world, array $players, int $x, int $y, int $z, int $surfaceY) : ?SpawnCandidateSnapshot{
		$pos = new Vector3($x, $y, $z);
		$nearest = null;
		foreach($players as $p){
			$d = $p->getPosition()->distance($pos);
			if($nearest === null || $d < $nearest){
				$nearest = $d;
			}
		}
		if($nearest === null){
			return null;
		}

		return new SpawnCandidateSnapshot(
			worldId: $world->getId(),
			x: $x,
			y: $y,
			z: $z,
			surfaceY: $surfaceY,
			band: SpawnBand::fromPosition($y, $surfaceY),
			biomeId: $world->getBiomeId($x, $y, $z),
			light: $world->getFullLightAt($x, $y, $z),
			blockTypeId: $world->getBlockAt($x, $y, $z)->getTypeId(),
			blockUnderTypeId: $world->getBlockAt($x, $y - 1, $z)->getTypeId(),
			densityCounts: [],
			populationCounts: [],
			difficulty: $world->getDifficulty(),
			nearestPlayerDistance: $nearest,
			time: $world->getTime() // world clock in ticks (World::getTime(); drives time-of-day and world-age filters)
		);
	}

}
