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

use IvanCraft623\MobPlugin\entity\animation\TameAnimation;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * Taming by using an item on the mob: it is used up and tames the mob by chance.
 *
 * @phpstan-require-extends Mob
 * @phpstan-require-implements Tameable
 */
trait TameByItemTrait{

	abstract protected function isTameItem(Item $item) : bool;

	/**
	 * Probability, from 0 to 1, that a tame item tames the mob.
	 */
	abstract protected function getTameChance() : float;

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if ($this->isTamed() || !$this->isTameItem($player->getInventory()->getItemInHand())) {
			return false;
		}

		Utils::popItemInHand($player);
		if ($this->random->nextFloat() < $this->getTameChance()) {
			$this->tame($player);
		} else {
			$this->broadcastAnimation(new TameAnimation($this, false));
		}

		return true;
	}
}