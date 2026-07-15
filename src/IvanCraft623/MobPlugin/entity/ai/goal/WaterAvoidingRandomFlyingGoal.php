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

use IvanCraft623\MobPlugin\entity\ai\utils\AirAndWaterPositionGenerator;
use IvanCraft623\MobPlugin\entity\ai\utils\HoverPositionGenerator;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;
use const M_PI;

class WaterAvoidingRandomFlyingGoal extends WaterAvoidingRandomStrollGoal {

	public function __construct(
		PathfinderMob $entity,
		float $speedModifier,
		float $probability = WaterAvoidingRandomStrollGoal::DEFAULT_PROBABILITY
	) {
		parent::__construct($entity, $speedModifier, $probability);
	}

	public function getPosition() : ?Vector3 {
		$directionVector = $this->entity->getDirectionPlane();
		return HoverPositionGenerator::getPos(
			entity: $this->entity,
			xzRadius: 8,
			yRadius: 7,
			directionX: $directionVector->x,
			directionZ: $directionVector->y,
			angle: M_PI / 2,
			maxHeight: 3,
			minHeight: 1
		) ?? AirAndWaterPositionGenerator::getPos(
			$this->entity, 8, 4, -2, $directionVector->x, $directionVector->y, M_PI / 2
		);
	}
}
