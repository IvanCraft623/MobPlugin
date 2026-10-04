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

use pocketmine\world\World;
use function intdiv;

/**
 * The fuller the moon, the likelier it passes: always at full moon, never at new moon.
 */
final class MoonPhaseChanceCondition implements SpawnCondition{
	/** Chance by moon phase, from full moon. */
	private const MOON_BRIGHTNESS = [1.0, 0.75, 0.5, 0.25, 0.0, 0.25, 0.5, 0.75];

	public function test(SpawnConditionContext $ctx) : bool{
		return self::MOON_BRIGHTNESS[intdiv($ctx->getTime(), World::TIME_FULL) & 7] > $ctx->getRandom()->nextFloat();
	}
}