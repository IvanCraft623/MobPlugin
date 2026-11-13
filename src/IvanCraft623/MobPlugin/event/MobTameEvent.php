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

namespace IvanCraft623\MobPlugin\event;

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\Tameable;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\entity\EntityEvent;
use pocketmine\player\Player;

/**
 * Called when a player's tame item has won the mob over, before the player becomes its owner.
 *
 * @phpstan-extends EntityEvent<Mob>
 */
class MobTameEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	/**
	 * @phpstan-param Mob&Tameable $entity
	 */
	public function __construct(
		Mob $entity,
		private readonly Player $tamer
	){
		$this->entity = $entity;
	}

	public function getTamer() : Player{
		return $this->tamer;
	}
}