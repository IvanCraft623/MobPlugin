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
use pocketmine\entity\EntitySizeInfo;
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

	/**
	 * The item state id of the block under the feet, which tells block variants apart
	 * (coarse dirt from dirt) but not placement state (snow layers).
	 */
	public function getBelowItemStateId() : int;

	public function getNearestPlayerDistance() : float;

	public function getTime() : int;

	public function getPopulation() : PopulationCounts;

	/**
	 * Whether a mob of this size, standing on the position, collides with no block.
	 */
	public function hasRoomFor(EntitySizeInfo $size) : bool;

	/**
	 * The spawner's random source, for conditions that roll a chance.
	 */
	public function getRandom() : Random;

	/**
	 * The world of the attempt, for conditions that need more than the values above.
	 */
	public function getWorld() : World;
}