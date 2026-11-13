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

use pocketmine\player\Player;

/**
 * A mob a player can tame. Its owner is the entity's owning entity.
 */
interface Tameable{

	public function isTamed() : bool;

	/**
	 * Whether it is tamed but has no owner, so the next player to adopt it becomes the owner.
	 */
	public function isOrphaned() : bool;

	/**
	 * Makes the player the owner, unless a listener cancels it.
	 *
	 * @return bool whether the mob is now tamed
	 */
	public function tame(Player $tamer) : bool;
}