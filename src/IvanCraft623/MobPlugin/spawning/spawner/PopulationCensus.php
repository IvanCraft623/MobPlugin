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

use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;
use function floor;

/**
 * Mob counts per band over the 9×9 chunk region around a chunk, counted lazily per
 * chunk from the live world.
 */
final class PopulationCensus{
	public const REGION_RADIUS = 4;

	/** @phpstan-var array<int, array{array<int, array<string, int>>, array<int, array<string, int>>}> chunk hash => [category counts, identifier counts], by band value */
	private array $chunks = [];

	/** @phpstan-var array<int, RegionPopulation> center chunk hash => region */
	private array $regions = [];

	public function __construct(
		private readonly SpawnPlacement $placement,
		private readonly SpawnRuleRegistry $registry,
		private readonly ?TimingsHandler $timings = null
	){}

	public function getRegionPopulation(int $chunkX, int $chunkZ) : RegionPopulation{
		$key = World::chunkHash($chunkX, $chunkZ);
		if(isset($this->regions[$key])){
			return $this->regions[$key];
		}

		$this->timings?->startTiming();
		try{
			return $this->regions[$key] = $this->countRegion($chunkX, $chunkZ);
		}finally{
			$this->timings?->stopTiming();
		}
	}

	private function countRegion(int $chunkX, int $chunkZ) : RegionPopulation{
		$categoryCounts = [];
		$identifierCounts = [];
		for($x = $chunkX - self::REGION_RADIUS; $x <= $chunkX + self::REGION_RADIUS; $x++){
			for($z = $chunkZ - self::REGION_RADIUS; $z <= $chunkZ + self::REGION_RADIUS; $z++){
				[$chunkCategories, $chunkIdentifiers] = $this->getChunkPopulation($x, $z);
				foreach($chunkCategories as $band => $counts){
					foreach($counts as $id => $count){
						$categoryCounts[$band][$id] = ($categoryCounts[$band][$id] ?? 0) + $count;
					}
				}
				foreach($chunkIdentifiers as $band => $counts){
					foreach($counts as $id => $count){
						$identifierCounts[$band][$id] = ($identifierCounts[$band][$id] ?? 0) + $count;
					}
				}
			}
		}

		return new RegionPopulation($categoryCounts, $identifierCounts);
	}

	/**
	 * Drops every count. The next query recounts from the world, which already holds any
	 * entity constructed since.
	 */
	public function clear() : void{
		$this->chunks = [];
		$this->regions = [];
	}

	/**
	 * @phpstan-return array{array<int, array<string, int>>, array<int, array<string, int>>}
	 */
	private function getChunkPopulation(int $chunkX, int $chunkZ) : array{
		$key = World::chunkHash($chunkX, $chunkZ);
		if(isset($this->chunks[$key])){
			return $this->chunks[$key];
		}

		$categoryCounts = [];
		$identifierCounts = [];
		foreach($this->placement->getWorld()->getChunkEntities($chunkX, $chunkZ) as $entity){
			if($entity->isClosed()){
				continue;
			}
			$identifier = $entity::getNetworkTypeId();
			$rules = $this->registry->get($identifier);
			if($rules === null){
				continue;
			}
			$pos = $entity->getPosition();
			// Aquatic mobs above the sea floor count as surface: the ground scan skips water.
			$band = SpawnBand::fromPosition($pos->y, $this->placement->getGroundY((int) floor($pos->x), (int) floor($pos->z)))->value;
			$categoryId = $rules->getCategoryId();
			$categoryCounts[$band][$categoryId] = ($categoryCounts[$band][$categoryId] ?? 0) + 1;
			$identifierCounts[$band][$identifier] = ($identifierCounts[$band][$identifier] ?? 0) + 1;
		}

		return $this->chunks[$key] = [$categoryCounts, $identifierCounts];
	}
}
