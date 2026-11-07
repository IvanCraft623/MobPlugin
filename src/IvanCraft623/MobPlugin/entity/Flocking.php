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

namespace IvanCraft623\MobPlugin\entity;

use pocketmine\block\Block;

/**
 * A mob that moves in a flock. Use FlockingTrait.
 */
interface Flocking{

	public function getFlockingParameters() : FlockingParameters;

	public function getFlockMembership() : FlockMembership;

	/**
	 * Whether the mob is somewhere it can flock (a fish: in water).
	 */
	public function canFlockNow() : bool;

	/**
	 * Whether the flock can move through a block (a fish: water). Followers steer away from the rest.
	 */
	public function canFlockThrough(Block $block) : bool;

	public function canFlockWith(Flocking $other) : bool;
}
