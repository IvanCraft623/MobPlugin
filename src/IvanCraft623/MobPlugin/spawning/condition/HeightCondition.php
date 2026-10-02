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

use IvanCraft623\MobPlugin\spawning\SpawnLiquid;

/**
 * Vanilla's height_filter: the range is tested on the block the mob stands on (one below
 * the feet), and on the feet too when they are in a liquid.
 */
final class HeightCondition implements SpawnCondition{
	public function __construct(
		private readonly ?int $min,
		private readonly ?int $max
	){
		if($min !== null && $max !== null && $min > $max){
			throw new \InvalidArgumentException("Height minimum ($min) must not exceed maximum ($max)");
		}
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$y = $ctx->getY();

		return $this->contains($y - 1) && ($ctx->getFeetLiquid() === SpawnLiquid::NONE || $this->contains($y));
	}

	private function contains(int $y) : bool{
		return ($this->min === null || $y >= $this->min) && ($this->max === null || $y <= $this->max);
	}
}
