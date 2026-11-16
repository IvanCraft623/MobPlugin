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

namespace IvanCraft623\MobPlugin\entity\ai\utils;

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\World;
use function abs;
use function count;
use function max;
use function min;
use const PHP_INT_MAX;

/**
 * Finds blocks by type, nearest first by Manhattan distance.
 */
class NearestBlockFinder {

	/** @var array<int, array<int, true>>|null block type id => its state ids, built when first needed */
	private static ?array $stateIdsByType = null;

	/**
	 * Yields the positions of blocks of the types in the box around the origin, nearest first, skipping unloaded
	 * chunks. Lazy: stopping early skips the rest of the box. Filter on the block's state with World::getBlockAt().
	 *
	 * @phpstan-return \Generator<int, Vector3, void, void>
	 */
	public static function find(World $world, Vector3 $origin, int $horizontalRange, int $verticalRange, int ...$typeIds) : \Generator{
		$stateIds = [];
		foreach ($typeIds as $typeId) {
			$stateIds += self::getStateIds($typeId);
		}

		$originX = $origin->getFloorX();
		$originY = $origin->getFloorY();
		$originZ = $origin->getFloorZ();

		$searchMinX = $originX - $horizontalRange;
		$searchMaxX = $originX + $horizontalRange;
		$searchMinY = max($world->getMinY(), $originY - $verticalRange);
		$searchMaxY = min($world->getMaxY() - 1, $originY + $verticalRange);
		$searchMinZ = $originZ - $horizontalRange;
		$searchMaxZ = $originZ + $horizontalRange;

		// Sections that may hold the types, and the distances they span
		/** @var array<int, array<int, array<int, PalettedBlockArray>>> $layers chunk x => section y => chunk z => first layer */
		$layers = [];
		$minDistance = PHP_INT_MAX;
		$maxDistance = 0;
		for ($chunkX = $searchMinX >> Chunk::COORD_BIT_SIZE; $chunkX <= $searchMaxX >> Chunk::COORD_BIT_SIZE; ++$chunkX) {
			$minX = max($searchMinX, $chunkX << Chunk::COORD_BIT_SIZE);
			$maxX = min($searchMaxX, ($chunkX << Chunk::COORD_BIT_SIZE) + Chunk::COORD_MASK);

			for ($chunkZ = $searchMinZ >> Chunk::COORD_BIT_SIZE; $chunkZ <= $searchMaxZ >> Chunk::COORD_BIT_SIZE; ++$chunkZ) {
				$chunk = $world->getChunk($chunkX, $chunkZ);
				if ($chunk === null) {
					continue;
				}

				$minZ = max($searchMinZ, $chunkZ << Chunk::COORD_BIT_SIZE);
				$maxZ = min($searchMaxZ, ($chunkZ << Chunk::COORD_BIT_SIZE) + Chunk::COORD_MASK);

				for ($sectionY = $searchMinY >> SubChunk::COORD_BIT_SIZE; $sectionY <= $searchMaxY >> SubChunk::COORD_BIT_SIZE; ++$sectionY) {
					$subChunk = $chunk->getSubChunk($sectionY);
					if ($subChunk->isEmptyFast()) {
						continue;
					}

					// Only the first layer is read, like World::getBlock()
					$layer = $subChunk->getBlockLayers()[0];
					if (!self::paletteHasAny($layer, $stateIds)) {
						continue;
					}

					$minY = max($searchMinY, $sectionY << SubChunk::COORD_BIT_SIZE);
					$maxY = min($searchMaxY, ($sectionY << SubChunk::COORD_BIT_SIZE) + SubChunk::COORD_MASK);
					$layers[$chunkX][$sectionY][$chunkZ] = $layer;
					$minDistance = min($minDistance, self::distanceToRange($minX, $maxX, $originX) + self::distanceToRange($minY, $maxY, $originY) + self::distanceToRange($minZ, $maxZ, $originZ));
					$maxDistance = max($maxDistance, max($originX - $minX, $maxX - $originX) + max($originY - $minY, $maxY - $originY) + max($originZ - $minZ, $maxZ - $originZ));
				}
			}
		}
		if (count($layers) === 0) {
			return;
		}

		// Shells of equal distance outwards, between the nearest and farthest section
		$maxDistanceY = max($originY - $searchMinY, $searchMaxY - $originY);
		for ($distance = $minDistance; $distance <= $maxDistance; ++$distance) {
			$reachX = min($distance, $horizontalRange);
			for ($x = $originX - $reachX; $x <= $originX + $reachX; ++$x) {
				// Skip chunks without candidate sections
				$layersAtX = $layers[$x >> Chunk::COORD_BIT_SIZE] ?? null;
				if ($layersAtX === null) {
					continue;
				}

				$restX = $distance - abs($x - $originX);
				// No height makes up the rest within the horizontal range
				if ($restX - $horizontalRange > $maxDistanceY) {
					continue;
				}

				$localX = $x & Chunk::COORD_MASK;
				$toY = $originY + $restX < $searchMaxY ? $originY + $restX : $searchMaxY;
				for ($y = $originY - $restX > $searchMinY ? $originY - $restX : $searchMinY; $y <= $toY; ++$y) {
					$restZ = $restX - abs($y - $originY);
					if ($restZ > $horizontalRange) {
						// Too much left for z at this height, jump to the first that isn't
						$y = $originY + $restX - $horizontalRange - 1;
						continue;
					}

					// And sections without them
					$layersAtXY = $layersAtX[$y >> SubChunk::COORD_BIT_SIZE] ?? null;
					if ($layersAtXY === null) {
						continue;
					}

					$localY = $y & SubChunk::COORD_MASK;
					// Both sides of the origin, once when they are the same
					for ($z = $originZ - $restZ; ; $z = $originZ + $restZ) {
						$layer = $layersAtXY[$z >> Chunk::COORD_BIT_SIZE] ?? null;
						if ($layer !== null && isset($stateIds[$layer->get($localX, $localY, $z & Chunk::COORD_MASK)])) {
							yield new Vector3($x, $y, $z);
						}
						if ($z >= $originZ) {
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * @return array<int, true>
	 */
	private static function getStateIds(int $typeId) : array{
		if (self::$stateIdsByType === null) {
			self::$stateIdsByType = [];
			foreach (RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $stateId => $block) {
				self::$stateIdsByType[$block->getTypeId()][$stateId] = true;
			}
		}

		return self::$stateIdsByType[$typeId] ?? [];
	}

	/**
	 * @param array<int, true> $stateIds
	 */
	private static function paletteHasAny(PalettedBlockArray $layer, array $stateIds) : bool{
		foreach ($layer->getPalette() as $stateId) {
			if (isset($stateIds[$stateId])) {
				return true;
			}
		}

		return false;
	}

	private static function distanceToRange(int $min, int $max, int $origin) : int{
		return abs($origin - max($min, min($max, $origin)));
	}
}