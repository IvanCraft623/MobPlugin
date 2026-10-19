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

use IvanCraft623\MobPlugin\entity\PathfinderMob;

class SwimIdleGoal extends Goal{

	private int $idleTicks = 0;

	public function __construct(
		protected PathfinderMob $mob,
		protected int $duration,
		protected float $successRate
	){
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		// Bedrock rolls the rate every tick, selection only runs every other one.
		return $this->mob->isInWater() &&
			!$this->mob->getNavigation()->isInProgress() &&
			$this->mob->getRandom()->nextFloat() < 1 - (1 - $this->successRate) ** 2;
	}

	public function canContinueToUse() : bool{
		return $this->idleTicks > 0 && $this->mob->isInWater();
	}

	public function start() : void{
		$this->idleTicks = $this->reducedTickDelay($this->duration);
	}

	public function tick() : void{
		$this->idleTicks--;
	}
}