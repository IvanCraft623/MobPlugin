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

use IvanCraft623\MobPlugin\spawning\spawner\GroundLevelCache;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

/**
 * How many mobs of a category or of a type are around a chunk, on the surface or
 * underground. Only entities whose identifier has registered spawn rules count.
 */
final class MobPopulation{
	use SingletonTrait;

	public function __construct(
		private readonly EntitySpawnBands $bands = new EntitySpawnBands()
	){}

	/**
	 * Counts over the 9×9 chunks around the chunk, as of now. Every call scans those
	 * chunks' entities again: keep the result rather than asking in a loop.
	 *
	 * @param SpawnRuleRegistry|null $registry the rules that say what counts; the shared registry by default
	 */
	public function around(World $world, int $chunkX, int $chunkZ, ?SpawnRuleRegistry $registry = null) : PopulationCounts{
		return $this->createCensus($world, new GroundLevelCache($world), $registry ?? SpawnRuleRegistry::getInstance())->getRegionPopulation($chunkX, $chunkZ);
	}

	public function getBands() : EntitySpawnBands{
		return $this->bands;
	}

	/**
	 * @internal one per world and tick, sharing the caller's ground memo and registry
	 */
	public function createCensus(World $world, GroundLevelCache $groundLevels, SpawnRuleRegistry $registry, ?TimingsHandler $timings = null) : PopulationCensus{
		return new PopulationCensus($world, $groundLevels, $this->bands, $registry, $timings);
	}
}
