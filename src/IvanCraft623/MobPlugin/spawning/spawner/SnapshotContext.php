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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnPosition;

/**
 * TEMPORARY (migration step 2 to 5): eager context over the batched pipeline's values.
 */
final class SnapshotContext implements SpawnConditionContext{
	private readonly SpawnLiquid $feetLiquid;

	public function __construct(
		private readonly SpawnPosition $position,
		private readonly RegionPopulation $population
	){
		$this->feetLiquid = SpawnLiquid::fromBlockTypeId($position->feetTypeId);
	}

	public function getBiomeId() : int{
		return $this->position->biomeId;
	}

	public function getBand() : SpawnBand{
		return $this->position->band;
	}

	public function getDifficulty() : int{
		return $this->position->difficulty;
	}

	public function getFeetLiquid() : SpawnLiquid{
		return $this->feetLiquid;
	}

	public function getX() : int{
		return $this->position->x;
	}

	public function getY() : int{
		return $this->position->y;
	}

	public function getZ() : int{
		return $this->position->z;
	}

	public function getGroundY() : int{
		return $this->position->groundY;
	}

	public function getLight() : int{
		return $this->position->light;
	}

	public function getWeatherLightPenalty() : int{
		return $this->position->weatherLightPenalty;
	}

	public function getBelowTypeId() : int{
		return $this->position->belowTypeId;
	}

	public function getNearestPlayerDistance() : float{
		return $this->position->nearestPlayerDistance;
	}

	public function getTime() : int{
		return $this->position->time;
	}

	public function getPopulation() : RegionPopulation{
		return $this->population;
	}
}
