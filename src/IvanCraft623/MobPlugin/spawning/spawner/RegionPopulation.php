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

final class RegionPopulation{
	/**
	 * @phpstan-param array<int, array<string, int>> $categoryCounts   band value => category id => count
	 * @phpstan-param array<int, array<string, int>> $identifierCounts band value => entity identifier => count
	 */
	public function __construct(
		private readonly array $categoryCounts = [],
		private readonly array $identifierCounts = []
	){}

	public function getCategoryCount(string $categoryId, SpawnBand $band) : int{
		return $this->categoryCounts[$band->value][$categoryId] ?? 0;
	}

	public function getIdentifierCount(string $identifier, SpawnBand $band) : int{
		return $this->identifierCounts[$band->value][$identifier] ?? 0;
	}
}
