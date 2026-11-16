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

namespace IvanCraft623\MobPlugin\entity\ai\goal\turtle;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\animal\Turtle;

/**
 * Takes the eggs it carries back to where it was born, now and then.
 */
final class GoHomeGoal extends Goal{

	/**
	 * @param int   $interval   average ticks between its trips home
	 * @param float $goalRadius blocks from home that count as being home
	 */
	public function __construct(
		private Turtle $turtle,
		private float $speedModifier,
		private int $interval,
		private float $goalRadius
	){
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		return $this->turtle->isPregnant() && $this->turtle->getRandom()->nextBoundedInt($this->reducedTickDelay($this->interval)) === 0;
	}

	public function canContinueToUse() : bool{
		return $this->turtle->isPregnant() && !$this->turtle->getNavigation()->isDone();
	}

	public function start() : void{
		if ($this->isHome()) {
			$this->turtle->arriveHome();

			return;
		}

		$home = $this->turtle->getHomePosition();
		$this->turtle->getNavigation()->moveToXYZ($home->x, $home->y, $home->z, $this->speedModifier);
	}

	public function stop() : void{
		$this->turtle->getNavigation()->stop();
	}

	public function tick() : void{
		if ($this->isHome()) {
			$this->turtle->arriveHome();
		}
	}

	private function isHome() : bool{
		return $this->turtle->getPosition()->distanceSquared($this->turtle->getHomePosition()) <= $this->goalRadius ** 2;
	}
}