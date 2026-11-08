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

namespace IvanCraft623\MobPlugin\entity\ai\goal\witch;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\monster\Witch;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\PotionType;

/**
 * Drinks a potion that helps with what the witch is going through.
 */
class DrinkPotionGoal extends Goal {

	private const FAR_TARGET_DISTANCE_SQUARED = 121; //blocks squared

	protected PotionType $potion;

	public function __construct(protected Witch $witch) {
	}

	public function canUse() : bool{
		if ($this->witch->isDrinking()) {
			return false;
		}

		$potion = $this->pickPotion();
		if ($potion === null) {
			return false;
		}
		$this->potion = $potion;

		return true;
	}

	public function start() : void{
		$this->witch->startDrinking($this->potion);
	}

	public function canContinueToUse() : bool{
		return $this->witch->isDrinking();
	}

	private function pickPotion() : ?PotionType{
		$witch = $this->witch;
		$effects = $witch->getEffects();
		$random = $witch->getRandom();

		if ($witch->getSensing()->isUnderwater() && !$effects->has(VanillaEffects::WATER_BREATHING()) && $random->nextFloat() < 0.15) {
			return PotionType::WATER_BREATHING;
		}
		if ($witch->isOnFire() && !$effects->has(VanillaEffects::FIRE_RESISTANCE()) && $random->nextFloat() < 0.15) {
			return PotionType::FIRE_RESISTANCE;
		}
		if ($witch->getHealth() < $witch->getMaxHealth() && $random->nextFloat() < 0.05) {
			return PotionType::HEALING;
		}
		$target = $witch->getTargetEntity();
		if ($target !== null && !$effects->has(VanillaEffects::SPEED()) && $random->nextFloat() < 0.25 &&
			$witch->getPosition()->distanceSquared($target->getPosition()) >= self::FAR_TARGET_DISTANCE_SQUARED
		) {
			return PotionType::SWIFTNESS;
		}

		return null;
	}
}
