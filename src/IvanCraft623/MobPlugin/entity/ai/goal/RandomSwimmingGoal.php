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
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\Water;
use pocketmine\math\Vector3;

class RandomSwimmingGoal extends RandomStrollGoal {

	private const POSITION_ATTEMPTS = 10;
	private const SURFACE_CLEARANCE = 2;

	public function __construct(
		PathfinderMob $entity,
		float $speedModifier,
		int $interval = 40,
		protected int $xzRange = 10,
		protected int $yRange = 7,
		protected bool $avoidSurface = false
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
			if ($position !== null && $world->getBlock($position) instanceof Water && !($this->avoidSurface && $this->isNearSurface($position))) {
				return $position;
			}
		}

		return null;
	}

	/**
	 * Whether fewer than {@see self::SURFACE_CLEARANCE} blocks of water are left above the position.
	 */
	private function isNearSurface(Vector3 $position) : bool{
		$world = $this->entity->getWorld();
		$x = $position->getFloorX();
		$z = $position->getFloorZ();
		$y = $position->getFloorY();
		for ($i = 1; $i <= self::SURFACE_CLEARANCE; ++$i) {
			if (!Utils::isWaterAt($world, $x, $y + $i, $z)) {
				return true;
			}
		}

		return false;
	}
}