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
use function array_keys;
use function count;

trait DamageTrackerTrait {

	/**
	 * Cap the number of tracked damagers; over a long-lived tracker (e.g. the Wither, which heals and can
	 * be attacked by an arbitrary number of distinct attackers over time) it would otherwise grow without
	 * bound since entity ids are monotonic and never reused.
	 */
	private const MAX_TRACKED_DAMAGERS = 128;

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

		if (count($this->damagers) > self::MAX_TRACKED_DAMAGERS) {
			$this->pruneStaleDamagers();
		}
	}

	public function getTotalDamageFrom(Entity $entity) : float{
		return $this->damagers[$entity->getId()] ?? 0;
	}

	/**
	 * Removes entries for entities that no longer exist (despawned/unloaded/closed) so the map stays
	 * bounded. Only the damage the Wither can actually still target matters anyway.
	 */
	public function pruneStaleDamagers() : void{
		$worldManager = $this->getWorld()->getServer()->getWorldManager();
		foreach (array_keys($this->damagers) as $entityId) {
			$entity = $worldManager->findEntity($entityId);
			if ($entity === null || $entity->closed || $entity->isFlaggedForDespawn()) {
				unset($this->damagers[$entityId]);
			}
		}
	}
}