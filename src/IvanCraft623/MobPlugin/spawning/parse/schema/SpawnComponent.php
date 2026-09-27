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
 * GENERATED from the official Mojang spawn-rule JSON schemas — do not edit by hand.
 *
 * Source  : metadata/json_schemas/server/spawn/1.21.50 (Mojang/bedrock-samples)
 * Contents: every "minecraft:*" condition component the schema declares for spawn-rule
 *           condition objects. Each case value is the component's unprefixed name; each
 *           comment names the schema file that defines the component's shape ($ref).
 *
 * Drift detection: SpawnConditionRegistry registers a parser for every case (enforced by
 * the PHPUnit suite) and the suite regenerates this artifact, so a component Mojang
 * renames, removes or adds forces a conscious update. Regenerate with:
 *
 *     php tools/spawn-rules/generate-schema.php
 *
 * The inventory is factual data (component names and schema references) derived from
 * material © Mojang AB, subject to the Minecraft EULA; it is redistributed solely for
 * interoperability with MobPlugin — see resources/spawning/NOTICE.md.
 */
enum SpawnComponent : string{
	case BIOME_FILTER = "biome_filter"; // $ref: ../../../client_server/common/1.21.20/Filter Group.json
	case BRIGHTNESS_FILTER = "brightness_filter"; // $ref: ./Spawn BrightnessFilter.json
	case DELAY_FILTER = "delay_filter"; // $ref: ./Spawn DelayFilter.json
	case DENSITY_LIMIT = "density_limit"; // $ref: ./Spawn DensityLimit.json
	case DIFFICULTY_FILTER = "difficulty_filter"; // $ref: ./Spawn DifficultyFilter.json
	case DISALLOW_SPAWNS_IN_BUBBLE = "disallow_spawns_in_bubble"; // $ref: ./Spawn DisallowSpawnInBubble.json
	case DISTANCE_FILTER = "distance_filter"; // $ref: ./Spawn DistanceFilter.json
	case HEIGHT_FILTER = "height_filter"; // $ref: ./Spawn HeightFilter.json
	case HERD = "herd"; // $ref: ./Spawn Herd.json
	case IS_EXPERIMENTAL = "is_experimental"; // $ref: ./Spawn IsExperimental.json
	case IS_PERSISTENT = "is_persistent"; // $ref: ./Spawn IsPersistant.json
	case MOB_EVENT_FILTER = "mob_event_filter"; // $ref: ./Spawn MobEventFilter.json
	case PERMUTE_TYPE = "permute_type"; // $ref: ./Spawn PermuteType.json
	case PLAYER_IN_VILLAGE_FILTER = "player_in_village_filter"; // $ref: ./Spawn PlayerInVillageFilter.json
	case SPAWN_EVENT = "spawn_event"; // $ref: ./Spawn MobEventFilter.json
	case SPAWNS_ABOVE_BLOCK_FILTER = "spawns_above_block_filter"; // $ref: ./Spawn SpawnAboveBlockFilter.json
	case SPAWNS_LAVA = "spawns_lava"; // $ref: ./Spawn SpawnInLava.json
	case SPAWNS_ON_BLOCK_FILTER = "spawns_on_block_filter"; // $ref: ../../common/1.20.50/Block Descriptor.json
	case SPAWNS_ON_BLOCK_PREVENTED_FILTER = "spawns_on_block_prevented_filter"; // $ref: ../../common/1.20.50/Block Descriptor.json
	case SPAWNS_ON_SURFACE = "spawns_on_surface"; // $ref: ./Spawn SpawnOnSurface.json
	case SPAWNS_UNDERGROUND = "spawns_underground"; // $ref: ./Spawn SpawnUnderground.json
	case SPAWNS_UNDERWATER = "spawns_underwater"; // $ref: ./Spawn SpawnUnderwater.json
	case WEIGHT = "weight"; // $ref: ./Spawn Weight.json
	case WORLD_AGE_FILTER = "world_age_filter"; // $ref: ./Spawn WorldAgeFilter.json
}
