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

/**
 * The vanilla world_age_filter component: the world's age in ticks must lie within
 * [min, max]. Either bound may be absent (unbounded).
 */
final class WorldAgeFilter implements SpawnCondition{
	public function __construct(
		private readonly ?int $min,
		private readonly ?int $max
	){
		if($this->min !== null && $this->max !== null && $this->min > $this->max){
			throw new \InvalidArgumentException("WorldAgeFilter minimum ($min) must not exceed maximum ($max)");
		}
	}

	public function getEvaluationCost() : int{
		return 1; // pure environment time compare
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$age = $ctx->env->getTime();
		if($this->min !== null && $age < $this->min){
			return false;
		}

		return !($this->max !== null && $age > $this->max);
	}
}
