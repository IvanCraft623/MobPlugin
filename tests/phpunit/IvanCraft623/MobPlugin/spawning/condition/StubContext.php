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

use IvanCraft623\MobPlugin\spawning\population\PopulationCounts;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use pocketmine\block\BlockTypeIds;
use pocketmine\utils\Random;
use pocketmine\world\World;

final class StubContext implements SpawnConditionContext{
	public int $populationReads = 0;

	public function __construct(
		public readonly int $biomeId = 1,
		public readonly SpawnBand $band = SpawnBand::SURFACE,
		public readonly int $difficulty = World::DIFFICULTY_NORMAL,
		public readonly SpawnLiquid $feetLiquid = SpawnLiquid::NONE,
		public readonly int $x = 0,
		public readonly int $y = 65,
		public readonly int $z = 0,
		public readonly int $light = 15,
		public readonly int $belowTypeId = BlockTypeIds::GRASS,
		public readonly float $nearestPlayerDistance = 30.0,
		public readonly int $time = 0,
		public readonly PopulationCounts $population = new PopulationCounts(),
		public readonly ?World $world = null,
		public readonly int $blockLight = 0,
		public readonly Random $random = new Random(0)
	){}

	public function getBiomeId() : int{
		return $this->biomeId;
	}

	public function getBand() : SpawnBand{
		return $this->band;
	}

	public function getDifficulty() : int{
		return $this->difficulty;
	}

	public function getFeetLiquid() : SpawnLiquid{
		return $this->feetLiquid;
	}

	public function getX() : int{
		return $this->x;
	}

	public function getY() : int{
		return $this->y;
	}

	public function getZ() : int{
		return $this->z;
	}

	public function getLight() : int{
		return $this->light;
	}

	public function getBlockLight() : int{
		return $this->blockLight;
	}

	public function getBelowTypeId() : int{
		return $this->belowTypeId;
	}

	public function getNearestPlayerDistance() : float{
		return $this->nearestPlayerDistance;
	}

	public function getTime() : int{
		return $this->time;
	}

	public function getPopulation() : PopulationCounts{
		$this->populationReads++;

		return $this->population;
	}

	public function getRandom() : Random{
		return $this->random;
	}

	public function getWorld() : World{
		return $this->world ?? throw new \LogicException("This StubContext has no world; pass one to the constructor");
	}
}
