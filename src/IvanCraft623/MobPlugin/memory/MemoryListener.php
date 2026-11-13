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

namespace IvanCraft623\MobPlugin\memory;

use IvanCraft623\MobPlugin\entity\Tameable;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;

/**
 * Feeds {@see Memories} with what happens in the world.
 */
final class MemoryListener implements Listener{

	/**
	 * @priority MONITOR
	 * @ignoreCancelled
	 */
	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void{
		$damager = $event->getDamager();
		if ($damager === null) {
			return;
		}

		$victim = $event->getEntity();
		$memories = Memories::getInstance();
		$memories->record($victim, Occurrence::HURT_BY, $damager);
		$memories->record($damager, Occurrence::HURT, $victim);
		//A tamed mob's hit counts as its owner's
		$hitter = $damager instanceof Tameable ? $damager->getOwningEntity() : $damager;
		if ($hitter instanceof Player) {
			$memories->record($victim, Occurrence::HURT_BY_PLAYER, $hitter);
		}
	}
}