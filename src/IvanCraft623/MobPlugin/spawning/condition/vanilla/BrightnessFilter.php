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
use function max;

/**
 * brightness_filter: full light level at the position within [min, max]; the weather
 * penalty comes from the context.
 */
final class BrightnessFilter implements SpawnCondition{
	public function __construct(
		private readonly int $min,
		private readonly int $max,
		private readonly bool $adjustForWeather
	){
		if($min > $max){
			throw new \InvalidArgumentException("BrightnessFilter minimum ($min) must not exceed maximum ($max)");
		}
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$light = $ctx->env->getLight();
		if($this->adjustForWeather){
			$light = max(0, $light - $ctx->weatherLightPenalty);
		}

		return $light >= $this->min && $light <= $this->max;
	}
}
