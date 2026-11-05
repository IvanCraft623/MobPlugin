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

use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\BlockPathType;
use function atan2;
use function cos;
use function floor;
use function sin;
use function sqrt;
use const M_PI;

/**
 * Walks to the wanted position, jumping up steps. Can also strafe, as long as the next step is walkable.
 */
class GroundMoveControl extends PathMoveControl {

	protected function drive() : void {
		match ($this->operation) {
			MoveOperation::STRAFE => $this->tickStrafe(),
			MoveOperation::MOVE_TO => $this->tickMoveTo(),
			MoveOperation::JUMPING => $this->tickJumping(),
			MoveOperation::WAIT => $this->mob->getMovementInput()->setForward(0),
		};
	}

	private function tickStrafe() : void {
		$location = $this->mob->getLocation();
		$speed = $this->speedModifier * $this->mob->getMovementSpeed();
		$forwardMovement = $this->forwardMovement;
		$sidewaysMovement = $this->sidewaysMovement;
		$strafe = sqrt(($forwardMovement ** 2) + ($sidewaysMovement ** 2));
		if ($strafe < 1) {
			$strafe = 1;
		}
		$strafe = $speed / $strafe;
		$forwardMovement *= $strafe;
		$sidewaysMovement *= $strafe;

		$sin = sin($location->yaw * (M_PI / 180));
		$cos = cos($location->yaw * (M_PI / 180));

		$x = $forwardMovement * $cos - $sidewaysMovement * $sin;
		$z = $sidewaysMovement * $cos + $forwardMovement * $sin;
		if (!$this->isWalkable($x, $z)) {
			$this->forwardMovement = 1;
			$this->sidewaysMovement = 0;
		}

		$this->mob->getMovementInput()->walk($speed);
		$this->mob->getMovementInput()->setForward($this->forwardMovement);
		$this->mob->getMovementInput()->setSideways($this->sidewaysMovement);

		$this->operation = MoveOperation::WAIT;
	}

	private function tickMoveTo() : void {
		$this->operation = MoveOperation::WAIT;

		$location = $this->mob->getLocation();
		$dx = $this->wantedPosition->x - $location->x;
		$dy = $this->wantedPosition->y - $location->y;
		$dz = $this->wantedPosition->z - $location->z;
		$distanceSquared = ($dx ** 2) + ($dy ** 2) + ($dz ** 2);

		if ($distanceSquared < 2.5E-7) { // 0.0005 ** 2
			$this->mob->getMovementInput()->setForward(0);
			return;
		}

		$yaw = $this->rotateLerp($location->yaw, (atan2($dz, $dx) * 180 / M_PI) - 90, 90);
		$this->mob->setRotation($yaw, $location->pitch);
		$this->mob->getMovementInput()->walk($this->speedModifier * $this->mob->getMovementSpeed());

		$this->jumpIfNeeded($dx, $dy, $dz);
	}

	private function isWalkable(float $x, float $z) : bool {
		$location = $this->mob->getLocation();

		return $this->navigation->getBlockPathType(
			(int) floor($location->x + $x),
			(int) floor($location->y),
			(int) floor($location->z + $z)
		) === BlockPathType::WALKABLE;
	}
}