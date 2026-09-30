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

/**
 * Census result for one spawn position: per-band entity counts within the population
 * region, by Bedrock identifier (density limits) and by MobCategory id (population caps).
 * Absent keys count as zero.
 */
final class SpawnCounts{
	/**
	 * @phpstan-param array<string, array{int, int}> $byIdentifier surface, cave
	 * @phpstan-param array<string, array{int, int}> $byCategory   surface, cave
	 */
	public function __construct(
		private readonly array $byIdentifier = [],
		private readonly array $byCategory = []
	){}

	public function identifier(string $identifier, SpawnBand $band) : int{
		return $this->byIdentifier[$identifier][$band === SpawnBand::SURFACE ? 0 : 1] ?? 0;
	}

	public function category(string $categoryId, SpawnBand $band) : int{
		return $this->byCategory[$categoryId][$band === SpawnBand::SURFACE ? 0 : 1] ?? 0;
	}
}
