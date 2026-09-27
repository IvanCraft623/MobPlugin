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

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use function acos;
use function array_values;
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
		private int $attemptsPerTick,
		private SpawnCensus $census
	){}

	/**
	 * One per-world collection pass; the caller gates on players online + non-peaceful.
	 *
	 * @phpstan-return list<SpawnCandidateSnapshot>
	 */
	public function collect(World $world) : array{
		$players = array_values($world->getPlayers());
		$snapshots = [];
		for($attempt = 0; $attempt < $this->attemptsPerTick; $attempt++){
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
		$this->census->countForBatch($world, $centers, $density, $population);
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
		if(!$world->isChunkLoaded($chunkX, $chunkZ)){
			return [];
		}
		$x = ($chunkX << 4) + mt_rand(0, 15);
		$z = ($chunkZ << 4) + mt_rand(0, 15);

		$snapshots = [];
		// Surface attempt: walk down from the column top to the first solid block —
		// a spawnable solid top hosts the herd, any other solid block cancels it.
		$topY = $world->getHighestBlockAt($x, $z) ?? $world->getMinY();
		$spawnableY = null;
		for($y = $topY; $y >= $world->getMinY(); $y--){
			$block = $world->getBlock(new Vector3($x, $y, $z));
			if($block->isSolid()){
				if($block->isFullCube() && !$block->isTransparent()){
					$spawnableY = $y;
				}
				break;
			}
		}
		if($spawnableY !== null && $spawnableY + 1 <= $world->getMaxY()){
			$surface = $this->snapshotCandidate($world, $players, $x, $spawnableY + 1, $z, $spawnableY);
			if($surface !== null){
				$snapshots[] = $surface;
			}
		}
		for($caveAttempt = 0; $caveAttempt < self::CAVE_ATTEMPTS_PER_COLUMN; $caveAttempt++){
			$y = mt_rand($world->getMinY(), max($world->getMinY(), $topY - 1));
			if($y <= $world->getMinY()){
				continue; // a ground block below is required; the world floor has none
			}
			$feet = $world->getBlock(new Vector3($x, $y, $z));
			$ground = $world->getBlock(new Vector3($x, $y - 1, $z));
			if(!$feet->isSolid() && $ground->isFullCube() && !$ground->isTransparent()){
				$snapshot = $this->snapshotCandidate($world, $players, $x, $y, $z, $topY);
				if($snapshot !== null){
					$snapshots[] = $snapshot;
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
			biomeId: $world->getBiomeId($x, $y, $z),
			light: $world->getFullLightAt($x, $y, $z),
			blockTypeId: $world->getBlock($pos)->getTypeId(),
			blockUnderTypeId: $world->getBlock(new Vector3($x, $y - 1, $z))->getTypeId(),
			densityCounts: [],
			populationCounts: [],
			difficulty: $world->getDifficulty(),
			nearestPlayerDistance: $nearest,
			time: $world->getTime() // world clock in ticks (World::getTime(); drives time-of-day and world-age filters)
		);
	}

}
