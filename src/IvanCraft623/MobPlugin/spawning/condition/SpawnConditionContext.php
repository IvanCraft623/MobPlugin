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

namespace IvanCraft623\MobPlugin\spawning\condition;

use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnEnvironment;

/**
 * Everything one spawn attempt can ask about, as a frozen value object; capability
 * sub-interfaces of SpawnEnvironment extend it (conditions degrade with instanceof).
 */
final class SpawnConditionContext{
	public function __construct(
		readonly public SpawnEnvironment $env,
		readonly public int $x,
		readonly public int $y,
		readonly public int $z,
		/** Habitat band of the position, precomputed once per attempt. */
		readonly public SpawnBand $band,
		/** PocketMine difficulty constant (World::DIFFICULTY_PEACEFUL .. DIFFICULTY_HARD). */
		readonly public int $difficulty,
		/** Light levels subtracted by the current weather (0, rain, thunder). */
		readonly public int $weatherLightPenalty,
		/** Distance (blocks) to the nearest relevant player, or null when there is none. */
		readonly public ?float $nearestPlayerDistance
	){}
}
