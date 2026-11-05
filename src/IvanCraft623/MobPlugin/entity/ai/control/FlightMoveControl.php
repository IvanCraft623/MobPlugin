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

namespace IvanCraft623\MobPlugin\entity\ai\control;

use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\PathfinderMob;

use function abs;
use function atan2;
use function sqrt;
use const M_PI;

class FlightMoveControl extends PathMoveControl {

	public function __construct(
		PathfinderMob $mob,
		PathNavigation $navigation,
		private int $maxPitchChange,
		private bool $noGravity
	) {
		parent::__construct($mob, $navigation);
	}

	protected function drive() : void {
		if ($this->operation === MoveOperation::MOVE_TO) {
			$location = $this->mob->getLocation();
			$this->operation = MoveOperation::WAIT;

			$this->mob->setHasGravity(false);

			$dx = $this->wantedPosition->x - $location->x;
			$dy = $this->wantedPosition->y - $location->y;
			$dz = $this->wantedPosition->z - $location->z;
			$distanceSquared = ($dx * $dx) + ($dy * $dy) + ($dz * $dz);

			if ($distanceSquared < 2.5E-7) { // 0.0005 ** 2
				$this->mob->getMovementInput()->setUpward(0);
				$this->mob->getMovementInput()->setForward(0);
				return;
			}

			if ($this->mob->onGround) {
				$speed = $this->speedModifier * $this->mob->getMovementSpeed();
			} else {
				$speed = $this->speedModifier * $this->mob->getAirMovementSpeed();
			}

			$yaw = $location->yaw;
			$pitch = $location->pitch;

			$distanceXZ = sqrt(($dx * $dx) + ($dz * $dz));
			if ($distanceXZ > 1.0E-5) {
				$yaw = $this->rotateLerp($location->yaw, (atan2($dz, $dx) * 180 / M_PI) - 90, 90);
				$this->mob->getMovementInput()->walk($speed);
			}

			if (abs($dy) > 1.0E-5) {
				$pitch = $this->rotateLerp(
					$location->pitch,
					-atan2($dy, $distanceXZ) * 180 / M_PI,
					$this->maxPitchChange
				);
				$this->mob->getMovementInput()->setUpward($dy > 0 ? $speed : -$speed);
			}
			$this->mob->setRotation($yaw, $pitch);
		} else {
			if (!$this->noGravity) {
				$this->mob->setHasGravity(true);
			}

			$this->mob->getMovementInput()->setUpward(0);
			$this->mob->getMovementInput()->setForward(0);
			$this->mob->getMovementInput()->walk(0);
		}
	}
}