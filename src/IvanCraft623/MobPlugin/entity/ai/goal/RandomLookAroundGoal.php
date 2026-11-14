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
use function cos;
use function deg2rad;
use function sin;

/**
 * Looks in a random direction now and then, within an angle of the way it was facing.
 */
class RandomLookAroundGoal extends Goal {

	protected float $baseYaw = 0.0; //degrees, the way it was facing when it started

	protected float $targetYaw = 0.0; //degrees, the way it is looking now

	protected int $lookTicksLeft = 0; //ticks left of the current look

	/**
	 * @param float $probability chance, out of 1 per check, of starting to look around
	 * @param int   $minLookTime least ticks a look lasts, the most is one less than $maxLookTime
	 * @param int   $maxLookTime see $minLookTime
	 * @param float $minAngle    degrees, least a look turns from where it faced when it started
	 * @param float $maxAngle    degrees, most a look turns from where it faced when it started
	 */
	public function __construct(
		protected Mob $entity,
		protected float $probability = 0.02,
		protected int $minLookTime = 20,
		protected int $maxLookTime = 40,
		protected float $minAngle = -180.0,
		protected float $maxAngle = 180.0
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return $this->entity->getRandom()->nextFloat() < $this->probability;
	}

	public function canContinueToUse() : bool{
		return $this->lookTicksLeft >= 0;
	}

	public function start() : void{
		$this->baseYaw = $this->entity->getLocation()->getYaw();
		$this->pickLook();
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		--$this->lookTicksLeft;

		$yaw = deg2rad($this->targetYaw);
		$this->entity->getLookControl()->setLookAt($this->entity->getEyePos()->add(-sin($yaw), 0, cos($yaw)));
	}

	protected function pickLook() : void{
		$random = $this->entity->getRandom();

		$this->targetYaw = $this->baseYaw + $this->minAngle + $random->nextFloat() * ($this->maxAngle - $this->minAngle);
		$this->lookTicksLeft = $this->minLookTime + (
			$this->maxLookTime > $this->minLookTime ? $random->nextBoundedInt($this->maxLookTime - $this->minLookTime) : 0
		);
	}
}
