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

use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * Implements Healable: uses up the item in hand to heal a hurt mob.
 *
 * @phpstan-require-extends Living
 * @phpstan-require-implements Healable
 */
trait HealableTrait{

	/**
	 * @return bool whether the mob was healed
	 */
	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$amount = $this->getHealAmount($player->getInventory()->getItemInHand());
		if ($amount <= 0 || $this->getHealth() >= $this->getMaxHealth()) {
			return false;
		}

		$event = new EntityRegainHealthEvent($this, $amount, EntityRegainHealthEvent::CAUSE_EATING);
		$this->heal($event);
		if (!$event->isCancelled()) {
			Utils::popItemInHand($player);
		}

		return true;
	}
}
