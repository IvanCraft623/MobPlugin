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

class SlimeFloatGoal extends Goal {

	public function __construct(protected Slime $mob) {
		$this->setFlags(Goal::FLAG_JUMP, Goal::FLAG_MOVE);
		$mob->getNavigation()->setCanFloat();
	}

	public function canUse() : bool{
		return $this->mob->isInWater() || $this->mob->isInLava();
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		if ($this->mob->getRandom()->nextFloat() < 0.8) {
			$this->mob->getJumpControl()->jump();
		}

		$this->mob->getMoveControl()->setWantedMovement(1.2);
	}
}
