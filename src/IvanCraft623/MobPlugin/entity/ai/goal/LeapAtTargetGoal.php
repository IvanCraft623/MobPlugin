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

use IvanCraft623\MobPlugin\entity\Mob;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;

class LeapAtTargetGoal extends Goal {

	protected Entity $target;

	public function __construct(
		protected Mob $mob,
		protected float $yMotion
	) {
		$this->setFlags(Goal::FLAG_JUMP, Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		//TODO: Check there is no passanger controlling movement

		if (!$this->mob->isOnGround()) {
			return false;
		}

		$target = $this->mob->getTargetEntity();
		if ($target === null) {
			return false;
		}

		$distanceSquared = $this->mob->getLocation()->distanceSquared($target->getLocation());
		if ($distanceSquared < 2 ** 2 || $distanceSquared > 4 ** 2) {
			return false;
		}

		if ($this->mob->getRandom()->nextBoundedInt($this->reducedTickDelay(5)) === 0) {
			$this->target = $target;
			return true;
		}

		return false;
	}

	public function canContinueToUse() : bool{
		return !$this->mob->isOnGround();
	}

	public function start() : void{
		$position = $this->mob->getLocation();
		$targetPosition = $this->target->getLocation();

		$leap = new Vector3($targetPosition->x - $position->x, 0.0, $targetPosition->z - $position->z);
		if ($leap->lengthSquared() > 0.0000001) {
			$leap = $leap->normalize()->multiply(0.4)->addVector($this->mob->getMotion()->multiply(0.2));
		}
		$leap->y = $this->yMotion;

		$this->mob->setMotion($leap);
	}

	public function stop() : void{
		unset($this->target);
	}
}
