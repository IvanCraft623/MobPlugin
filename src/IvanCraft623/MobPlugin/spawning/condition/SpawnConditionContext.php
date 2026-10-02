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
use pocketmine\utils\Random;
use pocketmine\world\World;

/**
 * Everything a condition may read about one attempt: the cached-by values plus the ones
 * that change from attempt to attempt.
 */
interface SpawnConditionContext extends CacheableConditionContext{
	public function getX() : int;

	public function getY() : int;

	public function getZ() : int;

	public function getLight() : int;

	public function getBlockLight() : int;

	public function getBelowTypeId() : int;

	public function getNearestPlayerDistance() : float;

	public function getTime() : int;

	public function getPopulation() : PopulationCounts;

	/**
	 * The spawner's random source, for conditions that roll a chance. A plain
	 * SpawnCondition runs on every attempt, so it may use it freely.
	 */
	public function getRandom() : Random;

	/**
	 * The world of the attempt, for conditions that need more than the values above. It is
	 * a per-attempt value, so a condition reading it is evaluated on every attempt.
	 */
	public function getWorld() : World;
}
