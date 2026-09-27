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
 * Plain-data snapshot of the world state one spawn candidate needs, taken by the
 * collector and consumed by the evaluator in the same tick. No World/Entity references.
 */
final class SpawnCandidateSnapshot{
	/**
	 * @phpstan-param array<string, BandCounts> $densityCounts Bedrock identifier =>
	 *     entity count per band (surface/cave) within the population region radius of the
	 *     candidate position; identifiers with no nearby mob may be absent.
	 * @phpstan-param array<string, BandCounts> $populationCounts MobCategory enum name =>
	 *     entity count per band, same region; categories with no nearby mob may be absent.
	 */
	public function __construct(
		/** World id (World::getId()) the candidate's state was snapshotted from. */
		public int $worldId,
		public int $x,
		public int $y,
		public int $z,
		public int $surfaceY,
		public int $biomeId,
		/** Combined light at the position, adjusted for time of day (World::getFullLightAt). */
		public int $light,
		public int $blockTypeId,
		public int $blockUnderTypeId,
		public array $densityCounts,
		public array $populationCounts,
		public int $difficulty,
		public float $nearestPlayerDistance,
		/** World clock (World::getTime()) when the snapshot was taken, in ticks. */
		public int $time = 0,
		/** Light levels subtracted by the current weather at snapshot time (0/rain/thunder). */
		public int $weatherLightPenalty = 0
	){}
}
