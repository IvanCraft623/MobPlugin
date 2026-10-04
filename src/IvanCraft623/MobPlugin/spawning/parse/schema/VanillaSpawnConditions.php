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

namespace IvanCraft623\MobPlugin\spawning\parse\schema;

/**
 * Auto-generated from the Mojang spawn-rule schemas (1.21.50) — do not edit by hand.
 *
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class VanillaSpawnConditions{

	public const BIOME_FILTER = "biome_filter"; // $ref: ../../../client_server/common/1.21.20/Filter Group.json
	public const BRIGHTNESS_FILTER = "brightness_filter"; // $ref: ./Spawn BrightnessFilter.json
	public const DELAY_FILTER = "delay_filter"; // $ref: ./Spawn DelayFilter.json
	public const DENSITY_LIMIT = "density_limit"; // $ref: ./Spawn DensityLimit.json
	public const DIFFICULTY_FILTER = "difficulty_filter"; // $ref: ./Spawn DifficultyFilter.json
	public const DISALLOW_SPAWNS_IN_BUBBLE = "disallow_spawns_in_bubble"; // $ref: ./Spawn DisallowSpawnInBubble.json
	public const DISTANCE_FILTER = "distance_filter"; // $ref: ./Spawn DistanceFilter.json
	public const HEIGHT_FILTER = "height_filter"; // $ref: ./Spawn HeightFilter.json
	public const HERD = "herd"; // $ref: ./Spawn Herd.json
	public const IS_EXPERIMENTAL = "is_experimental"; // $ref: ./Spawn IsExperimental.json
	public const IS_PERSISTENT = "is_persistent"; // $ref: ./Spawn IsPersistant.json
	public const MOB_EVENT_FILTER = "mob_event_filter"; // $ref: ./Spawn MobEventFilter.json
	public const PERMUTE_TYPE = "permute_type"; // $ref: ./Spawn PermuteType.json
	public const PLAYER_IN_VILLAGE_FILTER = "player_in_village_filter"; // $ref: ./Spawn PlayerInVillageFilter.json
	public const SPAWN_EVENT = "spawn_event"; // $ref: ./Spawn MobEventFilter.json
	public const SPAWNS_ABOVE_BLOCK_FILTER = "spawns_above_block_filter"; // $ref: ./Spawn SpawnAboveBlockFilter.json
	public const SPAWNS_LAVA = "spawns_lava"; // $ref: ./Spawn SpawnInLava.json
	public const SPAWNS_ON_BLOCK_FILTER = "spawns_on_block_filter"; // $ref: ../../common/1.20.50/Block Descriptor.json
	public const SPAWNS_ON_BLOCK_PREVENTED_FILTER = "spawns_on_block_prevented_filter"; // $ref: ../../common/1.20.50/Block Descriptor.json
	public const SPAWNS_ON_SURFACE = "spawns_on_surface"; // $ref: ./Spawn SpawnOnSurface.json
	public const SPAWNS_UNDERGROUND = "spawns_underground"; // $ref: ./Spawn SpawnUnderground.json
	public const SPAWNS_UNDERWATER = "spawns_underwater"; // $ref: ./Spawn SpawnUnderwater.json
	public const WEIGHT = "weight"; // $ref: ./Spawn Weight.json
	public const WORLD_AGE_FILTER = "world_age_filter"; // $ref: ./Spawn WorldAgeFilter.json

	private function __construct(){}
}
