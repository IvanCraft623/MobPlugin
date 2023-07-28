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

namespace IvanCraft623\MobPlugin\entity\ai\goal\slime;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\monster\Slime;

use pocketmine\entity\effect\VanillaEffects;

class SlimeRandomDirectionGoal extends Goal {

	protected float $chosenDegrees;

	protected int $nextRandomizeTime = 0;

	public function __construct(protected Slime $mob) {
		$this->setFlags(Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return $this->mob->getTargetEntityId() === null && (
			$this->mob->isOnGround() ||
			$this->mob->isInWater() ||
			$this->mob->isInLava() ||
			$this->mob->getEffects()->has(VanillaEffects::LEVITATION())
		);
	}

	public function tick() : void{
		if (--$this->nextRandomizeTime <= 0) {
			$this->nextRandomizeTime = $this->adjustedTickDelay(40 + $this->mob->getRandom()->nextBoundedInt(60));
			$this->chosenDegrees = $this->mob->getRandom()->nextBoundedInt(360);
		}

		$this->mob->getMoveControl()->setDirection($this->chosenDegrees, false);
	}
}
