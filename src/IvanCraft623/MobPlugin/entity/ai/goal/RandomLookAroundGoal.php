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
use function sin;
use const M_PI;

class RandomLookAroundGoal extends Goal {

	protected float $relX;

	protected float $relZ;

	protected int $lookTime;

	public function __construct(protected Mob $entity) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return $this->entity->getRandom()->nextFloat() < 0.02;
	}

	public function canContinueToUse() : bool{
		return $this->lookTime >= 0;
	}

	public function start() : void{
		$randomRadians = M_PI * 2 * $this->entity->getRandom()->nextFloat();

		$this->relX = cos($randomRadians);
		$this->relZ = sin($randomRadians);

		$this->lookTime = 20 + $this->entity->getRandom()->nextBoundedInt(20);
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$this->lookTime--;

		$this->entity->getLookControl()->setLookAt($this->entity->getEyePos()->add($this->relX, 0, $this->relZ));
	}
}
