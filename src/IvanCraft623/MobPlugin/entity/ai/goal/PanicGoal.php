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

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;

class PanicGoal extends Goal {

	public const WATER_CHECK_VERTICAL_DISTANCE = 1;

	protected Vector3 $target;

	protected bool $isRunning;

	public function __construct(
		protected PathfinderMob $entity,
		protected float $speedModifier
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool {
		if (!$this->shouldPanic()) {
			return false;
		}

		if ($this->entity->isOnFire()) {
			$target = $this->lookForWater($this->entity, 5);
			if ($target !== null) {
				$this->target = $target;
				return true;
			}
		}

		$target = $this->findRandomPosition();
		if ($target !== null) {
			$this->target = $target;
			return true;
		}

		return false;
	}

	protected function shouldPanic() : bool{
		//TODO: check if it is freezing
		return $this->entity->getExpirableLastDamageByEntity() !== null || $this->entity->isOnFire();
	}

	public function isRunning() : bool{
		return $this->isRunning;
	}

	protected function findRandomPosition() : ?Vector3 {
		return DefaultPositionGenerator::getPos($this->entity, 5, 4);
	}

	protected function lookForWater(Entity $entity, int $horizontalRange) : ?Vector3 {
		return null; //TODO: Implement this!
	}

	public function start() : void{
		$this->entity->getNavigation()->moveToXYZ($this->target->x, $this->target->y, $this->target->z, $this->speedModifier);
	}

	public function stop() : void {
		$this->isRunning = false;
	}

	public function canContinueToUse() : bool {
		return !$this->entity->getNavigation()->isDone();
	}
}
