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

namespace IvanCraft623\MobPlugin\entity\ai\navigation\locomotion;

use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;

/**
 * Walks like on the ground, then keeps going straight to its target, up walls, when the path is done.
 */
class WallClimberLocomotion extends GroundLocomotion{
	private ?Vector3 $target = null;

	private float $speedModifier = 1.0;

	public function onMoveRequested(Vector3 $target, float $speedModifier) : void{
		$this->target = $target->floor();
		$this->speedModifier = $speedModifier;
	}

	public function onStopped() : void{
		//Without a goal behind the movement nothing would ever replace an unreachable target.
		$this->target = null;
	}

	public function tickWithoutPath(PathfinderMob $mob) : void{
		if ($this->target === null) {
			return;
		}
		$mobPosition = $mob->getLocation();
		$mobWidthSqr = $mob->getSize()->getWidth() ** 2;
		$targetPosition = $this->target->add(0.5, 0.5, 0.5);
		if (!($targetPosition->distanceSquared($mobPosition) < $mobWidthSqr) && (
				!($mobPosition->y > $this->target->y) ||
				!($targetPosition->withComponents(null, $mobPosition->y, null)->distanceSquared($mobPosition) < $mobWidthSqr)
			)
		) {
			$mob->getMoveControl()->setWantedPosition($this->target, $this->speedModifier);
		} else {
			$this->target = null;
		}
	}
}