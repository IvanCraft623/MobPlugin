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

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

trait DamageTrackerTrait {

	/**
	 * @var array<int, float>
	 * entityId => damage
	 */
	protected array $damagers = [];

	public function attack(EntityDamageEvent $source) : void {
		if ($source->isCancelled()) {
			return;
		}

		if (!$source instanceof EntityDamageByEntityEvent) {
			return;
		}

		$damager = $source->getDamager();
		if ($damager === null) {
			return;
		}

		$this->damagers[$damager->getId()] = $this->getTotalDamageFrom($damager) + $source->getFinalDamage();
	}

	public function getTotalDamageFrom(Entity $entity) : float{
		return $this->damagers[$entity->getId()] ?? 0;
	}
}
