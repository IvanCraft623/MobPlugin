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

use IvanCraft623\MobPlugin\spawning\condition\CacheableCondition;
use IvanCraft623\MobPlugin\spawning\condition\CacheableConditionContext;

/**
 * A cacheable condition counting its calls; it passes in the given biome, or in all.
 */
final class CacheableSpyCondition implements CacheableCondition{
	public int $calls = 0;

	public function __construct(
		private readonly ?int $biomeId = null
	){}

	public function test(CacheableConditionContext $ctx) : bool{
		$this->calls++;

		return $this->biomeId === null || $ctx->getBiomeId() === $this->biomeId;
	}
}
