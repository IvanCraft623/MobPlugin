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

namespace IvanCraft623\MobPlugin\entity\ai\goal\polarbear;

use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\animal\PolarBear;
use IvanCraft623\MobPlugin\sound\EntityAttackSound;
use pocketmine\entity\Entity;

/**
 * Rears up on its hind legs for the last ticks before each hit.
 */
class PolarBearAttackGoal extends MeleeAttackGoal{

	private const REAR_TICKS = 10; //ticks before the hit

	public function __construct(private PolarBear $bear, float $speedModifier){
		parent::__construct($bear, $speedModifier);
	}

	public function stop() : void{
		$this->bear->setRearing(false);
		parent::stop();
	}

	public function tick() : void{
		$target = $this->bear->getTargetEntity();
		if ($target !== null) {
			if ($this->bear->getPerceivedDistanceSqrForMeleeAttack($target) > $this->getAttackReachSquared($target)) {
				//Out of reach, the hit has to be wound up again
				$this->resetAttackCooldown();
			} else {
				$this->bear->setRearing($this->getTicksToAttack() <= self::REAR_TICKS);
			}
		}

		parent::tick();
	}

	protected function attackEntity(Entity $target) : bool{
		$this->bear->broadcastSound(new EntityAttackSound($this->bear));

		return parent::attackEntity($target);
	}

	public function resetAttackCooldown() : void{
		$this->bear->setRearing(false);
		parent::resetAttackCooldown();
	}
}