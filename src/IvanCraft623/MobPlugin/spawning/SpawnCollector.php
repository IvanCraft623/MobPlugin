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

use IvanCraft623\MobPlugin\spawning\spawner\SpawnPlacement;

use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\World;
use function cos;
use function count;
use function floor;
use function min;
use function sin;
use function sqrt;
use const M_PI;
use const PHP_FLOAT_MAX;

/**
 * Stage 1 — Collect. Samples random positions in the spawn ring around the world's
 * players and reads the world facts the conditions need. Positions a mob provably can't
 * use (too close to a player, no room) are dropped here, before any rule is weighed. The
 * census is not run here: NaturalSpawner runs it only on positions the candidate cache keeps.
 */
final class SpawnCollector{
	/** Mobs never spawn closer than this to a player (vanilla despawns them immediately). */
	public const MIN_PLAYER_DISTANCE = 24;

	/** Outer spawn-ring radius, matching the wiki's simulation-distance-4 shell (24-44). */
	public const MAX_PLAYER_DISTANCE = 44;

	/** Cave attempts per column (vanilla scans every spawnable block; we sample). */
	public const CAVE_ATTEMPTS_PER_COLUMN = 2;

	public const MIN_DISTANCE_SQUARED = self::MIN_PLAYER_DISTANCE ** 2;

	private const MAX_DISTANCE_SQUARED = self::MAX_PLAYER_DISTANCE ** 2;

	public function __construct(
		private readonly Random $random
	){}

	/**
	 * One per-world collection pass; the caller gates on players online + non-peaceful.
	 *
	 * @phpstan-param list<Player> $players
	 *
	 * @phpstan-return list<SpawnPosition>
	 */
	public function collect(SpawnPlacement $placement, array $players, int $attempts) : array{
		$playerPositions = [];
		foreach($players as $player){
			$pos = $player->getPosition();
			$playerPositions[] = [$pos->x, $pos->y, $pos->z];
		}
		if(count($playerPositions) === 0){
			return [];
		}

		$positions = [];
		for($attempt = 0; $attempt < $attempts; $attempt++){
			$this->sampleColumn($placement, $playerPositions, $positions);
		}

		return $positions;
	}

	/**
	 * Horizontal offset from a player, uniformly distributed over the area of the
	 * MIN..MAX ring (inverse-CDF radius, continuous angle), so samples neither crowd the
	 * inner edge nor fall inside it.
	 *
	 * @phpstan-return array{float, float}
	 */
	public static function ringOffset(Random $random) : array{
		$radius = sqrt(self::MIN_DISTANCE_SQUARED + (self::MAX_DISTANCE_SQUARED - self::MIN_DISTANCE_SQUARED) * $random->nextFloat());
		$theta = 2.0 * M_PI * $random->nextFloat();

		return [cos($theta) * $radius, sin($theta) * $radius];
	}

	/**
	 * One column: a random chunk in the ring around a random player, a random column in
	 * it, then one surface attempt on the column's ground and a few cave attempts at
	 * random depths below it.
	 *
	 * @phpstan-param non-empty-list<array{float, float, float}> $players player x/y/z
	 * @phpstan-param list<SpawnPosition>                        $out
	 */
	private function sampleColumn(SpawnPlacement $placement, array $players, array &$out) : void{
		$world = $placement->getWorld();
		[$px, , $pz] = $players[$this->random->nextBoundedInt(count($players))];
		[$dx, $dz] = self::ringOffset($this->random);
		$chunkX = ((int) floor($px + $dx)) >> 4;
		$chunkZ = ((int) floor($pz + $dz)) >> 4;

		$chunk = $world->getChunk($chunkX, $chunkZ); // null when not loaded
		if($chunk === null || $chunk->isLightPopulated() !== true){
			return; // light not calculated yet — getFullLightAt() would wrongly read dark
		}
		$x = ($chunkX << 4) + $this->random->nextBoundedInt(16);
		$z = ($chunkZ << 4) + $this->random->nextBoundedInt(16);
		$minY = $world->getMinY();

		// Surface: the mob stands in the cell above the column's ground.
		$groundY = $placement->getGroundY($x, $z);
		$ground = $world->getBlockAt($x, $groundY, $z);
		if(SpawnPlacement::isSpawnableGround($ground) && $groundY + 2 < $world->getMaxY()){
			$position = $this->position($world, $players, $x, $groundY + 1, $z, $groundY, $ground);
			if($position !== null){
				$out[] = $position;
			}
		}

		// Caves: strictly below the ground, so genuinely underground (and banded CAVE).
		if($groundY - 1 <= $minY){
			return;
		}
		for($i = 0; $i < self::CAVE_ATTEMPTS_PER_COLUMN; $i++){
			$y = $this->random->nextRange($minY + 1, $groundY - 1);
			if($world->getBlockAt($x, $y, $z)->isSolid()){
				continue; // most uniform-depth samples land in rock; bail before more reads
			}
			$below = $world->getBlockAt($x, $y - 1, $z);
			if(!SpawnPlacement::isSpawnableGround($below)){
				continue;
			}
			$position = $this->position($world, $players, $x, $y, $z, $groundY, $below);
			if($position !== null){
				$out[] = $position;
			}
		}
	}

	/**
	 * Reads one position's facts, or null when it is provably unusable: within the
	 * minimum distance of any player, or no feet/head room. Liquid feet are allowed (the
	 * rule index only offers liquid-declaring rules there); the applier re-checks the
	 * live world with the matched rule's needs.
	 *
	 * @phpstan-param non-empty-list<array{float, float, float}> $players
	 */
	private function position(World $world, array $players, int $x, int $y, int $z, int $groundY, Block $below) : ?SpawnPosition{
		$cx = $x + 0.5;
		$cz = $z + 0.5;
		$nearestSquared = PHP_FLOAT_MAX;
		foreach($players as [$px, $py, $pz]){
			$d = ($px - $cx) ** 2 + ($py - $y) ** 2 + ($pz - $cz) ** 2;
			if($d < self::MIN_DISTANCE_SQUARED){
				return null;
			}
			$nearestSquared = min($nearestSquared, $d);
		}
		$feet = $world->getBlockAt($x, $y, $z);
		if($feet->isSolid() || $world->getBlockAt($x, $y + 1, $z)->isSolid()){
			return null;
		}

		return new SpawnPosition(
			worldId: $world->getId(),
			x: $x,
			y: $y,
			z: $z,
			groundY: $groundY,
			band: SpawnBand::fromPosition($y, $groundY),
			biomeId: $world->getBiomeId($x, $y, $z),
			light: $world->getFullLightAt($x, $y, $z),
			feetTypeId: $feet->getTypeId(),
			belowTypeId: $below->getTypeId(),
			difficulty: $world->getDifficulty(),
			nearestPlayerDistance: sqrt($nearestSquared),
			time: $world->getTime()
		);
	}
}
