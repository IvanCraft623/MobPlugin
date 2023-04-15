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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\ai\utils\LandPositionGenerator;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\block\Water;
use pocketmine\math\Vector3;

class WaterAvoidingRandomStrollGoal extends RandomStrollGoal {

	public const DEFAULT_PROBABILITY = 0.001;

	public function __construct(
		PathfinderMob $entity, float $speedModifier,
		protected float $probability = self::DEFAULT_PROBABILITY
	) {
		parent::__construct($entity, $speedModifier);
	}

	public function getPosition() : ?Vector3{
		if ($this->entity->getWorld()->getBlock($this->entity->getPosition()) instanceof Water) {
			return LandPositionGenerator::getPos($this->entity, 15, 7) ?? parent::getPosition();
		}

		if ($this->entity->getRandom()->nextFloat() >= $this->probability) {
			return LandPositionGenerator::getPos($this->entity, 10, 7);
		}

		return parent::getPosition();
	}
}
