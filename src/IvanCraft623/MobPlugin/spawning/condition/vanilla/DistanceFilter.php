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
 * distance_filter: nearest-player distance within [min, max]; fails when unknown.
 */
final class DistanceFilter implements SpawnCondition{
	public function __construct(
		private readonly ?float $min,
		private readonly ?float $max
	){
		if($this->min !== null && $this->max !== null && $this->min > $this->max){
			throw new \InvalidArgumentException("DistanceFilter minimum ($min) must not exceed maximum ($max)");
		}
	}

	public function getEvaluationCost() : int{
		return 1; // pure context-field compare
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$distance = $ctx->nearestPlayerDistance;
		if($distance === null){
			return false;
		}
		if($this->min !== null && $distance < $this->min){
			return false;
		}

		return !($this->max !== null && $distance > $this->max);
	}
}
