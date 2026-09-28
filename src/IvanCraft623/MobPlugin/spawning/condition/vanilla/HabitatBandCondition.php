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
use IvanCraft623\MobPlugin\spawning\plan\HabitatConstrained;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use function count;

/**
 * The spawns_on_surface / spawns_underground marker pair compiled to one condition.
 * A single marker pins its band; both markers add no condition (vanilla: any band).
 */
final class HabitatBandCondition implements SpawnCondition, HabitatConstrained{
	/** @phpstan-param list<SpawnBand> $bands */
	public function __construct(
		private readonly array $bands
	){
		if(count($this->bands) === 0){
			throw new \InvalidArgumentException("HabitatBandCondition requires at least one band");
		}
	}

	/**
	 * @phpstan-return list<SpawnBand>
	 */
	public function getAllowedHabitatBands() : array{
		return $this->bands;
	}

	public function getEvaluationCost() : int{
		return 1; // pure context-field compare
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$positionBand = $ctx->band;
		foreach($this->bands as $band){
			if($band === $positionBand){
				return true;
			}
		}

		return false;
	}
}
