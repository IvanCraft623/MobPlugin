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

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\MobCategory;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * Entity census for the population-control caps: counts mobs per habitat band within
 * the population region around a position. Main-thread only.
 *
 * Hot path: the collector batches a tick's candidates into one entity pass
 * (countForBatch) instead of one full entity scan per candidate.
 */
final class SpawnCensus{
	/** Radius (blocks) approximating the Bedrock 9x9 chunk population region. */
	public const SPAWN_REGION_RADIUS = 72;

	/**
	 * One entity pass for a tick's candidate positions: tallies per-identifier and
	 * per-category band counts for every center at once (same world, so per-entity
	 * world/closed checks and the entity's band — which depends only on its own
	 * position, not the center — happen once per entity).
	 *
	 * Hot path: counts accumulate into mutable int pairs and are materialized into
	 * immutable BandCounts once per center, avoiding object allocation on every
	 * matching (entity, center) pair.
	 *
	 * @phpstan-param list<Vector3> $centers
	 * @phpstan-param list<array<string, BandCounts>> $densityOut
	 * @phpstan-param list<array<string, BandCounts>> $populationOut
	 */
	public function countForBatch(World $world, array $centers, array &$densityOut, array &$populationOut) : void{
		// Mutable [surface, cave] int lists keyed by identifier/category per center.
		$density = [];
		$population = [];
		foreach($centers as $_){
			$density[] = [];
			$population[] = [];
		}

		$radiusSquared = self::SPAWN_REGION_RADIUS ** 2;
		foreach($world->getEntities() as $entity){
			if($entity->isClosed() || $entity->getPosition()->getWorld() !== $world){
				continue;
			}
			$entityPos = $entity->getPosition();
			$typeId = $entity::getNetworkTypeId();
			$categoryValue = $entity instanceof Mob ? $entity->getMobCategory()->value : null;

			// The band is a property of the entity's own column, not of any center, so
			// it is computed once per entity (not once per entity/center pair).
			$band = self::getSurfaceBand($world, $entityPos);
			$bandKey = $band === SpawnBand::SURFACE ? 0 : 1;

			foreach($centers as $i => $center){
				if($entityPos->distanceSquared($center) > $radiusSquared){
					continue;
				}
				$density[$i][$typeId] ??= [0, 0];
				$density[$i][$typeId][$bandKey]++;
				if($categoryValue !== null){
					$population[$i][$categoryValue] ??= [0, 0];
					$population[$i][$categoryValue][$bandKey]++;
				}
			}
		}

		foreach($centers as $i => $_){
			$densityOut[$i] = self::materialize($density[$i]);
			$populationOut[$i] = self::materialize($population[$i]);
		}
	}

	/**
	 * Materializes the mutable [surface, cave] accumulators into immutable BandCounts.
	 *
	 * @phpstan-param array<string, array{0: int, 1: int}> $accum keyed by identifier/category
	 *
	 * @phpstan-return array<string, BandCounts>
	 */
	private static function materialize(array $accum) : array{
		$result = [];
		foreach($accum as $key => $counts){
			$result[$key] = new BandCounts($counts[0], $counts[1]);
		}

		return $result;
	}

	/**
	 * Counts same-category mobs in the spawn region radius that belong to the given band,
	 * per the Bedrock population control rules.
	 */
	public function countCategoryNearby(World $world, MobCategory $category, Vector3 $pos, SpawnBand $band) : int{
		$count = 0;
		$radiusSquared = self::SPAWN_REGION_RADIUS ** 2;
		foreach($world->getEntities() as $entity){
			if($entity->isClosed() || !$entity instanceof Mob || $entity->getPosition()->getWorld() !== $world){
				continue;
			}
			if($entity->getMobCategory() !== $category){
				continue;
			}
			$entityPos = $entity->getPosition();
			if($entityPos->distanceSquared($pos) > $radiusSquared){
				continue;
			}
			if(self::getSurfaceBand($world, $entityPos) === $band){
				$count++;
			}
		}

		return $count;
	}

	/**
	 * A mob counts as surface when it is above the highest block of its own column (where
	 * it spawned — approximated by where it is now), cave otherwise.
	 */
	public static function getSurfaceBand(World $world, Vector3 $pos) : SpawnBand{
		$surfaceY = $world->getHighestBlockAt($pos->getFloorX(), $pos->getFloorZ()) ?? $world->getMinY();

		return SpawnBand::fromPosition($pos->getY(), $surfaceY);
	}
}
