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

use pocketmine\entity\Living;
use pocketmine\event\entity\EntityEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Called when a player has fed a mob. An animal is by then in love, or a baby that has
 * grown.
 *
 * @phpstan-extends EntityEvent<Living>
 */
class MobFeedEvent extends EntityEvent{

	public function __construct(
		Living $entity,
		private readonly Player $player,
		private readonly Item $item
	){
		$this->entity = $entity;
	}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getItem() : Item{
		return clone $this->item;
	}
}
