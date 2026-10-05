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

namespace IvanCraft623\MobPlugin\despawning;

use IvanCraft623\MobPlugin\event\MobFeedEvent;
use IvanCraft623\MobPlugin\event\MobSpawnCause;
use IvanCraft623\MobPlugin\event\MobSpawnEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\Listener;

/**
 * Keeps the despawn profiles up to date with what happens to their entities.
 */
final class DespawnListener implements Listener{

	public function __construct(
		private readonly DespawnRuleRegistry $registry
	){}

	/**
	 * @priority MONITOR
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void{
		$this->registry->getProfile($event->getEntity())?->markActive();
	}

	/**
	 * @priority MONITOR
	 */
	public function onEntityItemPickup(EntityItemPickupEvent $event) : void{
		// A mob that picks up an item becomes persistent. No event follows the pickup, so this
		// repeats the checks that let it go through.
		if($event->getItem()->getCount() > 0 && $event->getInventory() !== null){
			$this->registry->getProfile($event->getEntity())?->setPersistent();
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onMobSpawn(MobSpawnEvent $event) : void{
		$parent = $event->getParent();
		$persistent = match($event->getCause()){
			MobSpawnCause::BREEDING, MobSpawnCause::SPAWN_EGG => true,
			MobSpawnCause::SPLIT => $parent !== null && $this->registry->getProfile($parent)?->isPersistent() === true,
			default => false
		};
		if($persistent){
			$this->registry->getProfile($event->getEntity())?->setPersistent();
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onMobFeed(MobFeedEvent $event) : void{
		$this->registry->getProfile($event->getEntity())?->setPersistent();
	}
}