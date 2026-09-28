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
use function intdiv;

/**
 * Entity census for the population-control caps: counts mobs per habitat band within the
 * population region around a position. Main-thread only.
 *
 * An entity counts when it resolves to a registered population-control category (via the
 * injected resolver backed by SpawnRuleRegistry), so both MobPlugin mobs and bound
 * raw-PMMP mobs (e.g. Squid) are counted — players/items/projectiles/unbound mobs are not.
 */
final class SpawnCensus{
	/** Radius (blocks) approximating the Bedrock 9x9 chunk population region. */
	public const SPAWN_REGION_RADIUS = 72;

	/** Per-batch cache of each column's solid ground Y, keyed by column hash. */
	/** @var array<int, int> */
	private array $columnGroundCache = [];

	/**
	 * @phpstan-param \Closure(Entity): ?MobCategory $categoryResolver resolves an entity
	 *     to the population-control category it counts against, or null to skip it
	 */
	public function __construct(
		private readonly \Closure $categoryResolver
	){}

	/**
	 * One entity pass for a tick's candidate positions: tallies per-identifier and
	 * per-category band counts for every center at once. The entity's band (paid as a
	 * block-array lookup) is computed lazily, only for entities in range of a center.
	 *
	 * Hot path: counts accumulate into mutable int pairs and are materialized into
	 * immutable BandCounts once per center, avoiding object allocation on every
	 * matching (entity, center) pair.
	 *
	 * Entities are bucketed by chunk, and each center only visits the chunk neighborhood
	 * its population region can overlap. This turns the natural O(entities × centers)
	 * distance cross-product into O(entities) + O(centers × region-chunks), so spawning
	 * scales with the mob population actually near a center rather than the whole world.
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
		$this->columnGroundCache = [];

		// Bucket tally-eligible entities by their chunk so each center only iterates the
		// chunk neighborhood it can reach instead of every entity in the world. The
		// population-control category is resolved once here, alongside each entity.
		/** @phpstan-var array<int, list<array{Entity, MobCategory}>> $chunkBuckets */
		$chunkBuckets = [];
		foreach($world->getEntities() as $entity){
			if($entity->isClosed() || $entity->getPosition()->getWorld() !== $world){
				continue;
			}
			$category = ($this->categoryResolver)($entity);
			if($category === null){
				continue; // players/items/projectiles/unbound mobs count against nothing
			}
			$pos = $entity->getPosition();
			$chunkKey = World::chunkHash($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4);
			$chunkBuckets[$chunkKey] ??= [];
			$chunkBuckets[$chunkKey][] = [$entity, $category];
		}

		$radiusSquared = self::SPAWN_REGION_RADIUS ** 2;
		$chunkSpread = intdiv(self::SPAWN_REGION_RADIUS, 16); // blocks→chunks; a full-chunk step past this is always out of range
		foreach($centers as $i => $center){
			$centerChunkX = ((int) $center->x) >> 4;
			$centerChunkZ = ((int) $center->z) >> 4;
			for($dx = -$chunkSpread; $dx <= $chunkSpread; $dx++){
				for($dz = -$chunkSpread; $dz <= $chunkSpread; $dz++){
					$bucket = $chunkBuckets[World::chunkHash($centerChunkX + $dx, $centerChunkZ + $dz)] ?? null;
					if($bucket === null){
						continue;
					}
					foreach($bucket as [$entity, $category]){
						$this->tallyNear($world, $entity, $category, $center, $radiusSquared, $density[$i], $population[$i]);
					}
				}
			}
		}

		foreach($centers as $i => $_){
			$densityOut[$i] = self::materialize($density[$i]);
			$populationOut[$i] = self::materialize($population[$i]);
		}
	}

	/**
	 * Adds one entity to a center's tally when it lies within the population region
	 * radius. The entity's band is resolved lazily, only for entities actually in range.
	 *
	 * @phpstan-param MobCategory $category the entity's pre-resolved population-control category
	 * @phpstan-param array<string, array{0: int, 1: int}> $density mutable accum
	 * @phpstan-param array<string, array{0: int, 1: int}> $population mutable accum
	 */
	private function tallyNear(World $world, Entity $entity, MobCategory $category, Vector3 $center, int $radiusSquared, array &$density, array &$population) : void{
		$entityPos = $entity->getPosition();
		if($entityPos->distanceSquared($center) > $radiusSquared){
			return;
		}
		$typeId = $entity::getNetworkTypeId();
		$categoryId = $category->id;
		$bandKey = $this->entityBandKey($world, $entityPos);
		$density[$typeId] ??= [0, 0];
		$density[$typeId][$bandKey]++;
		$population[$categoryId] ??= [0, 0];
		$population[$categoryId][$bandKey]++;
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
	 * The entity's band key (0 = surface, 1 = cave) for its current position. Water is
	 * treated as transparent: the boundary is the column's solid ground (the highest
	 * spawnable platform), so aquatic mobs swimming above the sea floor count as surface —
	 * squids are an animal-surface population, not cave.
	 *
	 * @phpstan-return int<0, 1>
	 */
	private function entityBandKey(World $world, Vector3 $pos) : int{
		$groundY = $this->columnGroundY($world, $pos->getFloorX(), $pos->getFloorZ());

		return SpawnBand::fromPosition($pos->getY(), $groundY) === SpawnBand::SURFACE ? 0 : 1;
	}

	/**
	 * The highest solid, full-cube, opaque block Y in a column (the spawnable ground),
	 * scanning down from the column top. Air and liquids (water/lava) are skipped, so an
	 * ocean's floor — not its water surface — is the reference. Falls back to the column
	 * top (or world min when empty) when no spawnable platform is found. Cached per column
	 * for the current batch.
	 */
	private function columnGroundY(World $world, int $x, int $z) : int{
		$cacheKey = ($x & 0xFFFFFF) | (($z & 0xFFFFFF) << 24);
		if(isset($this->columnGroundCache[$cacheKey])){
			return $this->columnGroundCache[$cacheKey];
		}

		$minY = $world->getMinY();
		$topY = $world->getHighestBlockAt($x, $z) ?? $minY;
		$groundY = $topY;
		for($y = $topY; $y >= $minY; $y--){
			$block = $world->getBlockAt($x, $y, $z, false);
			if($block->isSolid() && $block->isFullCube() && !$block->isTransparent()){
				$groundY = $y;
				break;
			}
		}

		return $this->columnGroundCache[$cacheKey] = $groundY;
	}
}
