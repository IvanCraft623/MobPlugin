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
 * World state queries spawn conditions evaluate against, position-relative, never a live
 * World — keeps condition classes decoupled and unit-testable.
 */
interface SpawnEnvironment{

	/**
	 * Bedrock biome id stored in the chunk column at the evaluated position.
	 */
	public function getBiomeId() : int;

	/**
	 * Y of the highest non-air block of the evaluated position's column (heightmap).
	 * Columns without any block resolve to the world minimum.
	 */
	public function getSurfaceY() : int;

	/**
	 * Full light level (0-15) at the evaluated position, without weather adjustment; the
	 * checker applies the weather penalty from SpawnConditionContext itself.
	 */
	public function getLight() : int;

	/**
	 * PocketMine block type id of the feet block at the evaluated position.
	 */
	public function getBlockTypeId() : int;

	/**
	 * PocketMine block type id of the block directly underneath the evaluated feet
	 * position.
	 */
	public function getBelowBlockTypeId() : int;

	/**
	 * Number of entities with the given Bedrock identifier near the evaluated position
	 * (band-scoped), for density-limit checks.
	 */
	public function countNearby(string $identifier) : int;

	/**
	 * World clock in ticks (World::getTime()) when the snapshot was taken — the closest
	 * available approximation of Bedrock's world age, used by world_age_filter checks.
	 */
	public function getTime() : int;

	/**
	 * Time of day at the evaluated position: the world clock modulo a full 24000-tick
	 * day (World::getTimeOfDay()). Drives day/night spawn gating; compare against
	 * World::TIME_* constants (e.g. World::TIME_NIGHT, World::TIME_SUNRISE).
	 */
	public function getTimeOfDay() : int;
}
