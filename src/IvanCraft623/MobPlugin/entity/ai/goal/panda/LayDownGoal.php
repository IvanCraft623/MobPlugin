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

namespace IvanCraft623\MobPlugin\entity\ai\goal\panda;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\animal\Panda;

/**
 * Lies down on its back for a long while, now and then.
 */
final class LayDownGoal extends Goal{

	/**
	 * @param int $startInterval average ticks between lying down
	 * @param int $stopInterval  average ticks of lying down
	 */
	public function __construct(
		private Panda $panda,
		private int $startInterval,
		private int $stopInterval
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		return $this->panda->isIdle() &&
			!$this->panda->isInWater() &&
			$this->panda->getRandom()->nextBoundedInt($this->reducedTickDelay($this->startInterval)) === 0;
	}

	public function canContinueToUse() : bool{
		return !$this->panda->isInWater() && $this->panda->getRandom()->nextBoundedInt($this->reducedTickDelay($this->stopInterval)) !== 0;
	}

	public function start() : void{
		$this->panda->getNavigation()->stop();
		$this->panda->setLayingDown(true);
	}

	public function stop() : void{
		$this->panda->setLayingDown(false);
	}
}