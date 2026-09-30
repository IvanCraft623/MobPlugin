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
use pocketmine\world\World;

/**
 * Knows only the cache key; every per-attempt getter throws PointInputRequired.
 */
final class KeyContext implements SpawnConditionContext{
	public function __construct(
		private readonly int $biomeId,
		private readonly SpawnBand $band,
		private readonly int $difficulty,
		private readonly SpawnLiquid $feetLiquid
	){}

	public static function from(SpawnConditionContext $ctx) : self{
		return new self($ctx->getBiomeId(), $ctx->getBand(), $ctx->getDifficulty(), $ctx->getFeetLiquid());
	}

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
		throw PointInputRequired::point();
	}

	public function getY() : int{
		throw PointInputRequired::point();
	}

	public function getZ() : int{
		throw PointInputRequired::point();
	}

	public function getGroundY() : int{
		throw PointInputRequired::point();
	}

	public function getLight() : int{
		throw PointInputRequired::point();
	}

	public function getWeatherLightPenalty() : int{
		throw PointInputRequired::point();
	}

	public function getBelowTypeId() : int{
		throw PointInputRequired::point();
	}

	public function getNearestPlayerDistance() : float{
		throw PointInputRequired::point();
	}

	public function getTime() : int{
		throw PointInputRequired::point();
	}

	public function getPopulation() : RegionPopulation{
		throw PointInputRequired::population();
	}

	public function getWorld() : World{
		throw PointInputRequired::point();
	}
}
