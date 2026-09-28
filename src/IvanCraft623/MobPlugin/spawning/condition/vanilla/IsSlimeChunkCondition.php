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

namespace IvanCraft623\MobPlugin\spawning\condition\vanilla;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\slime\SlimeChunkChecker;

/**
 * Bedrock's slime-chunk rule: the spawn chunk must be a slime chunk (a coordinate-derived
 * 1-in-10 chunk property, no world seed involved — unlike Java). See SlimeChunkChecker.
 */
final class IsSlimeChunkCondition implements SpawnCondition{
	public function getEvaluationCost() : int{
		return 5; // runs the Mersenne-Twister walk — the priciest single check
	}

	public function test(SpawnConditionContext $ctx) : bool{
		return SlimeChunkChecker::isSlimeChunk($ctx->x >> 4, $ctx->z >> 4);
	}
}
