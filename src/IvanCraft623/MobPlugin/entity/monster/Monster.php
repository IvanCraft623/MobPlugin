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

namespace IvanCraft623\MobPlugin\entity\monster;

use IvanCraft623\MobPlugin\entity\MobCategory;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use function floor;

abstract class Monster extends PathfinderMob implements Enemy {
	//TODO!

	public function getMobCategory() : MobCategory{
		return MobCategory::MONSTER();
	}

	public function shouldDespawnInPeaceful() : bool{
		return true;
	}

	public function getXpDropAmount() : int{
		if ($this->hasBeenDamagedByPlayer()) {
			return 5;
		}

		return 0;
	}

	public function isSunSensitive() : bool{
		return false;
	}

	public function tickAi() : void{
		parent::tickAi();

		if (!$this->isOnFire() && $this->isSunSensitive()) {
			$world = $this->getWorld();
			$pos = $this->getEyePos();
			if ($world->getSkyLightReduction() <= 3 &&
				$world->getPotentialBlockSkyLightAt((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z)) === 15 &&
				!$this->isInWater() //TODO: Powder snow also prevents this
			) {
				$helmet = $this->getArmorInventory()->getHelmet();
				if ($helmet->isNull()) {
					$this->setOnFire(8);
				}/* elseif ($helmet instanceof Durable) {
					//TODO: Not sure if these are the right values
					$helmet->applyDamage(mt_rand(0, 2));
					if ($helmet->isBroken()) {
						$helmet = VanillaItems::AIR();
					}
					$this->getArmorInventory()->setHelmet($helmet);
				}*/
			}
		}
	}
}
