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
use pocketmine\math\Vector3;

class RandomStrollGoal extends Goal {

	public const DEFAULT_INTERVAL = 120;

	protected Vector3 $wantedPosition;

	protected bool $forceTrigger = false;

	public function __construct(
		protected PathfinderMob $entity,
		protected float $speedModifier,
		protected int $interval = self::DEFAULT_INTERVAL,
		protected bool $checkNoActionTime = true
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		// TODO: is Vehicle check

		if (!$this->forceTrigger && (
			($this->checkNoActionTime && $this->entity->getNoActionTime() >= 100) ||
			$this->entity->getRandom()->nextBoundedInt($this->reducedTickDelay($this->interval)) !== 0
		)) {
			return false;
		}

		$position = $this->getPosition();
		if ($position === null) {
			return false;
		}

		$this->wantedPosition = $position;
		$this->forceTrigger = false;

		return true;
	}

	public function getPosition() : ?Vector3{
		return DefaultPositionGenerator::getPos($this->entity, 10, 7);
	}

	public function canContinueToUse() : bool{
		return !$this->entity->getNavigation()->isDone();
	}

	public function start() : void{
		$this->entity->getNavigation()->moveToXYZ($this->wantedPosition->x, $this->wantedPosition->y, $this->wantedPosition->z, $this->speedModifier);
	}

	public function stop() : void{
		$this->entity->getNavigation()->stop();
		parent::stop();
	}

	public function trigger() : void{
		$this->forceTrigger = true;
	}

	public function setInterval(int $interval) : void{
		$this->interval = $interval;
	}
}
