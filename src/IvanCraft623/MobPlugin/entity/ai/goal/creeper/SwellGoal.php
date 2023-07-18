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

namespace IvanCraft623\MobPlugin\entity\ai\goal\creeper;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\monster\Creeper;

class SwellGoal extends Goal {

	public const DEFAULT_START_DISTANCE = 2.5;
	public const DEFAULT_STOP_DISTANCE = 6;

	public function __construct(
		protected Creeper $entity,
		protected float $startDistance = self::DEFAULT_START_DISTANCE,
		protected float $stopDistance = self::DEFAULT_STOP_DISTANCE
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		if ($this->entity->isIgnited()) {
			return false; //Creeper is forced to explode.
		}

		$target = $this->entity->getTargetEntity();
		return $this->entity->isSwelling() ||
			($target !== null && $this->entity->getPosition()->distanceSquared($target->getPosition()) < ($this->startDistance ** 2));
	}

	public function start() : void{
		$this->entity->getNavigation()->stop();
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$target = $this->entity->getTargetEntity();

		if ($target === null ||
			$this->entity->getPosition()->distanceSquared($target->getPosition()) > ($this->stopDistance ** 2) ||
			!$this->entity->getSensing()->canSee($target)
		) {
			$this->entity->setSwelling(false);
		} else {
			$this->entity->setSwelling();
		}
	}
}
