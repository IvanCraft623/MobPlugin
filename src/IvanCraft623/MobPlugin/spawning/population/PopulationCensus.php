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

namespace IvanCraft623\MobPlugin\spawning\population;

use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\spawner\GroundLevelCache;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use pocketmine\entity\Entity;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;
use function abs;
use function floor;

/**
 * Mob counts of one world for one tick: per chunk, counted from the live world the first
 * time the chunk is needed, and summed over the 9×9 chunks around a chunk.
 */
final class PopulationCensus{
	private const REGION_RADIUS = 4;

	/** @phpstan-var array<int, PopulationCounts> chunk hash => counts */
	private array $chunks = [];

	/** @phpstan-var array<int, array{int, int, PopulationCounts}> center chunk hash => chunk X, chunk Z, counts */
	private array $regions = [];

	/** @phpstan-var array<class-string<Entity>, array{string, string}|false> entity class => category id and identifier; false if not counted */
	private array $classes = [];

	public function __construct(
		private readonly World $world,
		private readonly GroundLevelCache $groundLevels,
		private readonly EntitySpawnBands $spawnBands,
		private readonly SpawnRuleRegistry $registry,
		private readonly ?TimingsHandler $timings = null
	){}

	public function getRegionPopulation(int $chunkX, int $chunkZ) : PopulationCounts{
		$key = World::chunkHash($chunkX, $chunkZ);
		if(isset($this->regions[$key])){
			return $this->regions[$key][2];
		}

		$this->timings?->startTiming();
		try{
			$counts = new PopulationCounts();
			for($x = $chunkX - self::REGION_RADIUS; $x <= $chunkX + self::REGION_RADIUS; $x++){
				for($z = $chunkZ - self::REGION_RADIUS; $z <= $chunkZ + self::REGION_RADIUS; $z++){
					$counts->merge($this->getChunkPopulation($x, $z));
				}
			}
			$this->regions[$key] = [$chunkX, $chunkZ, $counts];

			return $counts;
		}finally{
			$this->timings?->stopTiming();
		}
	}

	/**
	 * Counts a mob spawned since its chunk was counted, as vanilla does after each spawn.
	 * A chunk not counted yet will find the mob in the world.
	 */
	public function add(Entity $entity) : void{
		if($entity->isClosed()){
			return;
		}
		$counted = $this->classify($entity);
		if($counted === null){
			return;
		}
		$pos = $entity->getPosition();
		$chunkX = ((int) floor($pos->x)) >> 4;
		$chunkZ = ((int) floor($pos->z)) >> 4;
		$chunk = $this->chunks[World::chunkHash($chunkX, $chunkZ)] ?? null;
		if($chunk === null){
			return; // then no region summed so far covers it either
		}

		[$band, $categoryId, $identifier] = $counted;
		$chunk->add($band, $categoryId, $identifier);
		foreach($this->regions as [$regionX, $regionZ, $region]){
			if(abs($regionX - $chunkX) <= self::REGION_RADIUS && abs($regionZ - $chunkZ) <= self::REGION_RADIUS){
				$region->add($band, $categoryId, $identifier);
			}
		}
	}

	private function getChunkPopulation(int $chunkX, int $chunkZ) : PopulationCounts{
		$key = World::chunkHash($chunkX, $chunkZ);
		if(isset($this->chunks[$key])){
			return $this->chunks[$key];
		}

		$counts = new PopulationCounts();
		foreach($this->world->getChunkEntities($chunkX, $chunkZ) as $entity){
			if($entity->isClosed() || $entity->isFlaggedForDespawn()){
				continue;
			}
			$counted = $this->classify($entity);
			if($counted !== null){
				$counts->add(...$counted);
			}
		}

		return $this->chunks[$key] = $counts;
	}

	/**
	 * @phpstan-return array{SpawnBand, string, string}|null band, category id and identifier; null if not counted
	 */
	private function classify(Entity $entity) : ?array{
		$class = $this->classes[$entity::class] ??= $this->classifyClass($entity);
		if($class === false){
			return null;
		}

		$band = $this->spawnBands->get($entity);
		if($band === null){
			// Aquatic entities above the sea floor are surface: the ground scan skips water.
			$pos = $entity->getPosition();
			$band = SpawnBand::fromPosition($pos->y, $this->groundLevels->getGroundY((int) floor($pos->x), (int) floor($pos->z)));
			$this->spawnBands->set($entity, $band);
		}

		return [$band, $class[0], $class[1]];
	}

	/**
	 * @phpstan-return array{string, string}|false
	 */
	private function classifyClass(Entity $entity) : array|false{
		$identifier = $entity::getNetworkTypeId();
		$rules = $this->registry->get($identifier);

		return $rules === null ? false : [$rules->getCategoryId(), $identifier];
	}
}