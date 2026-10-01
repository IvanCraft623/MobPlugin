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

use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\spawner\RegionPopulation;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use pocketmine\world\World;

interface SpawnConditionContext{
	public function getBiomeId() : int;

	public function getBand() : SpawnBand;

	public function getDifficulty() : int;

	public function getFeetLiquid() : SpawnLiquid;

	public function getX() : int;

	public function getY() : int;

	public function getZ() : int;

	public function getGroundY() : int;

	public function getLight() : int;

	public function getBelowTypeId() : int;

	public function getNearestPlayerDistance() : float;

	public function getTime() : int;

	public function getPopulation() : RegionPopulation;

	/**
	 * The world of the attempt, for conditions that need more than the values above. It is
	 * a per-attempt value, so a condition reading it is evaluated on every attempt.
	 */
	public function getWorld() : World;
}
