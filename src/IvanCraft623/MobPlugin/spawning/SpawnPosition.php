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
 * Immutable world facts about one sampled spawn position, read by the collector in a
 * single pass. Plain data: no World/Entity references, so evaluation is testable
 * without a server.
 */
final class SpawnPosition{
	public function __construct(
		/** World id (World::getId()) the position was sampled from. */
		public readonly int $worldId,
		/** Feet block coordinates. */
		public readonly int $x,
		public readonly int $y,
		public readonly int $z,
		/** Y of the column's spawnable ground (see SpawnPlacement::groundY()). */
		public readonly int $groundY,
		/** Habitat band, derived from y against groundY. */
		public readonly SpawnBand $band,
		public readonly int $biomeId,
		/** Combined light at the feet, adjusted for time of day (World::getFullLightAt). */
		public readonly int $light,
		/** PocketMine block type id of the feet block. */
		public readonly int $feetTypeId,
		/** PocketMine block type id of the block under the feet. */
		public readonly int $belowTypeId,
		/** PocketMine difficulty constant of the world. */
		public readonly int $difficulty,
		/** Distance (blocks) to the nearest player in the world. */
		public readonly float $nearestPlayerDistance,
		/** World clock (World::getTime()) in ticks. */
		public readonly int $time,
		/** Light levels subtracted by the current weather (0/rain/thunder). */
		public readonly int $weatherLightPenalty = 0
	){}
}
