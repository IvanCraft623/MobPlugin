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
use pocketmine\entity\Human;
use pocketmine\entity\Living as PMLiving;
use pocketmine\event\entity\EntityDamageByEntityEvent;

trait NeutralMobTrait {

	abstract public function getTargetEntity() : ?Entity;

	abstract public function setTargetEntity(?Entity $target) : void;

	abstract public function setLastDamageByEntity(?EntityDamageByEntityEvent $type) : void;

	abstract public function getRemainingAngerTime() : int;

	abstract public function setRemainingAngerTime(int $ticks) : void;

	abstract public function startAngerTimer() : void;

	public function updateAnger(bool $value) : void{
		$target = $this->getTargetEntity();
		if ($target !== null && !$target->isAlive()) {
			$this->stopBeingAngry();
		} else {
			if (!$this->isAngry() && $target !== null) {
				$this->startAngerTimer();
			}

			if ($this->isAngry() && ($target === null || $target instanceof Human || !$value)) {
				$this->setRemainingAngerTime($this->getRemainingAngerTime() - 1);
				if ($this->getRemainingAngerTime() === 0) {
					$this->stopBeingAngry();
				}
			}
		}
	}

	public function isAngryAt(Entity $entity) : bool{
		if (!$entity instanceof PMLiving || !$this->canAttack($entity)) {
			return false;
		}

		return $this->getTargetEntityId() === $entity->getId();
	}

	public function isAngry() : bool{
		return $this->getRemainingAngerTime() > 0;
	}

	public function stopBeingAngry() : void{
		$this->setLastDamageByEntity(null);
		$this->setTargetEntity(null);
		$this->setRemainingAngerTime(0);
	}
}
