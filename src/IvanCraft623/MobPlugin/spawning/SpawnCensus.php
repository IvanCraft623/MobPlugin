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

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function count;
use function floor;
use function intdiv;

/**
 * Entity census for population caps and density limits: counts mobs per habitat band
 * within the population region around each position. Main-thread only.
 *
 * An entity counts when the injected resolver maps it to a population-control category,
 * so both MobPlugin mobs and bound raw-PMMP mobs (e.g. Squid) count — players, items,
 * projectiles and unbound mobs don't.
 */
final class SpawnCensus{
	/** Radius (blocks) approximating the Bedrock 9x9 chunk population region. */
	public const SPAWN_REGION_RADIUS = 72;

	/**
	 * @phpstan-param \Closure(Entity): ?MobCategory $categoryResolver resolves an entity
	 *     to the population-control category it counts against, or null to skip it
	 */
	public function __construct(
		private readonly \Closure $categoryResolver
	){}

	/**
	 * One entity pass for a batch of positions in one world.
	 *
	 * Entities are flattened once into [x, y, z, identifier, category, band] rows bucketed
	 * by chunk, and each center only visits the chunks its region can overlap:
	 * O(entities) + O(centers × region-chunks). An entity's band (a column ground scan) is
	 * resolved lazily, once, and only if some center reaches it.
	 *
	 * @phpstan-param list<Vector3> $centers
	 *
	 * @phpstan-return list<SpawnCounts> aligned with $centers
	 */
	public function count(World $world, array $centers) : array{
		/** @phpstan-var array<int, list<array{float, float, float, string, string, int}>> $chunkBuckets */
		$chunkBuckets = [];
		foreach($world->getEntities() as $entity){
			if($entity->isClosed()){
				continue;
			}
			$category = ($this->categoryResolver)($entity);
			if($category === null){
				continue;
			}
			$pos = $entity->getPosition();
			$chunkKey = World::chunkHash(((int) floor($pos->x)) >> 4, ((int) floor($pos->z)) >> 4);
			$chunkBuckets[$chunkKey][] = [$pos->x, $pos->y, $pos->z, $entity::getNetworkTypeId(), $category->id, -1];
		}

		$results = [];
		$radiusSquared = self::SPAWN_REGION_RADIUS ** 2;
		$chunkSpread = intdiv(self::SPAWN_REGION_RADIUS, 16) + 1; // +1: the center may sit anywhere in its chunk
		foreach($centers as $center){
			/** @phpstan-var array<string, array{int, int}> $density */
			$density = [];
			/** @phpstan-var array<string, array{int, int}> $population */
			$population = [];
			if(count($chunkBuckets) !== 0){
				$centerChunkX = ((int) floor($center->x)) >> 4;
				$centerChunkZ = ((int) floor($center->z)) >> 4;
				for($dx = -$chunkSpread; $dx <= $chunkSpread; $dx++){
					for($dz = -$chunkSpread; $dz <= $chunkSpread; $dz++){
						$key = World::chunkHash($centerChunkX + $dx, $centerChunkZ + $dz);
						if(!isset($chunkBuckets[$key])){
							continue;
						}
						foreach($chunkBuckets[$key] as &$row){
							$ex = $row[0] - $center->x;
							$ey = $row[1] - $center->y;
							$ez = $row[2] - $center->z;
							if($ex * $ex + $ey * $ey + $ez * $ez > $radiusSquared){
								continue;
							}
							if($row[5] < 0){
								// Water is skipped by the ground scan, so aquatic mobs above the sea
								// floor count as surface (squids are an animal-surface population).
								$groundY = SpawnPlacement::groundY($world, (int) floor($row[0]), (int) floor($row[2]));
								$row[5] = SpawnBand::fromPosition($row[1], $groundY) === SpawnBand::SURFACE ? 0 : 1;
							}
							$density[$row[3]] = self::increment($density[$row[3]] ?? [0, 0], $row[5]);
							$population[$row[4]] = self::increment($population[$row[4]] ?? [0, 0], $row[5]);
						}
						unset($row);
					}
				}
			}
			$results[] = new SpawnCounts(self::materialize($density), self::materialize($population));
		}

		return $results;
	}

	/**
	 * @phpstan-param array{int, int} $pair [surface, cave]
	 *
	 * @phpstan-return array{int, int}
	 */
	private static function increment(array $pair, int $bandKey) : array{
		if($bandKey === 0){
			$pair[0]++;
		}else{
			$pair[1]++;
		}

		return $pair;
	}

	/**
	 * @phpstan-param array<string, array{int, int}> $accum
	 *
	 * @phpstan-return array<string, BandCounts>
	 */
	private static function materialize(array $accum) : array{
		$result = [];
		foreach($accum as $key => [$surface, $cave]){
			$result[$key] = new BandCounts($surface, $cave);
		}

		return $result;
	}
}
