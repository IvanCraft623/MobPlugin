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

use IvanCraft623\MobPlugin\entity\ai\utils\DefaultPositionGenerator;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\block\Water;
use pocketmine\math\Vector3;

class RandomSwimmingGoal extends RandomStrollGoal {

	private const POSITION_ATTEMPTS = 10;

	public function __construct(
		PathfinderMob $entity,
		float $speedModifier,
		int $interval = 40,
		protected int $xzRange = 10,
		protected int $yRange = 7
	) {
		parent::__construct($entity, $speedModifier, $interval);
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return $this->entity->isInWater() && parent::canUse();
	}

	public function getPosition() : ?Vector3{
		$world = $this->entity->getWorld();
		for ($i = 0; $i < self::POSITION_ATTEMPTS; ++$i) {
			$position = DefaultPositionGenerator::getPos($this->entity, $this->xzRange, $this->yRange);
			if ($position !== null && $world->getBlock($position) instanceof Water) {
				return $position;
			}
		}

		return null;
	}
}
