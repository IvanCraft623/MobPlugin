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
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\Water;

use function abs;
use function atan2;
use function max;
use function min;
use function sqrt;
use const M_PI;

class AquaticMoveControl extends PathMoveControl {

	private const BUOYANCY = 0.005;
	private const PITCH_STEP = 15.0;
	private const ARRIVAL_DISTANCE = 0.0005;
	private const EPSILON = 1.0E-5;
	/** Degrees of turn left below which the mob still swims at full speed */
	private const FULL_SPEED_TURN = 10.0;
	/** Degrees of turn left, past the full speed ones, that slow it down to a stop */
	private const STOP_TURN_RANGE = 50.0;

	/** Pull on a mob while air touches its body */
	private const SURFACE_GRAVITY = 0.02;
	/** Surface gravity left while the mob swims */
	private const SURFACE_GRAVITY_SWIMMING = 0.004;
	/** Vertical share of a swimmer's speed per block of height difference */
	private const VERTICAL_GAIN = 5.0;
	private const MAX_VERTICAL_SHARE = 1.25;
	/** Vertical push of swimmers that ease up and down, as a fraction of their speed */
	private const GENTLE_VERTICAL_PUSH = 0.1;

	/**
	 * @param float      $landSpeed multiplier of the speed while out of water
	 * @param bool       $buoyant   whether water pushes the mob up
	 * @param SwimThrust $thrust    how the swim is split between horizontal and vertical
	 */
	public function __construct(
		PathfinderMob $mob,
		PathNavigation $navigation,
		private int $maxPitchChange,
		private int $maxYawChange,
		private float $landSpeed,
		private bool $buoyant,
		private SwimThrust $thrust
	) {
		parent::__construct($mob, $navigation);
	}

	private bool $swimming = false;

	public function isSwimming() : bool {
		return $this->swimming;
	}

	/**
	 * Whether the mob swims this tick: in water and with a locomotion that swims toward the position.
	 */
	protected function shouldSwim() : bool {
		if (!$this->mob->isInWater()) {
			return false;
		}

		$position = $this->operation === MoveOperation::MOVE_TO ? $this->wantedPosition : $this->mob->getPosition();

		return $this->navigation->getLocomotion()->swimsAt($this->mob->getWorld(), $this->navigation->getRules(), $position);
	}

	protected function drive() : void {
		$this->swimming = $this->shouldSwim();
		$this->mob->setHasGravity(!$this->swimming);
		if ($this->swimming) {
			if ($this->buoyant) {
				$this->mob->addMotion(0, self::BUOYANCY, 0);
			}
			//Fully underwater mobs float, air touching the body pulls them back down
			if ($this->mob->getWaterImmersion() < 1) {
				$moving = $this->operation === MoveOperation::MOVE_TO;
				$this->mob->addMotion(0, -($moving ? self::SURFACE_GRAVITY_SWIMMING : self::SURFACE_GRAVITY), 0);
			}
		}

		$this->mob->getMovementInput()->setUpward(0);

		match ($this->operation) {
			MoveOperation::MOVE_TO => $this->tickMoveTo(),
			MoveOperation::JUMPING => $this->tickJumping(),
			MoveOperation::WAIT,
			MoveOperation::STRAFE => $this->idle(),
		};
	}

	private function idle() : void {
		$this->operation = MoveOperation::WAIT;
		$this->mob->getMovementInput()->walk(0);
	}

	private function tickMoveTo() : void {
		$this->operation = MoveOperation::WAIT;

		$location = $this->mob->getLocation();
		$dx = $this->wantedPosition->x - $location->x;
		$dy = $this->wantedPosition->y - $location->y;
		$dz = $this->wantedPosition->z - $location->z;
		$distance = sqrt(($dx ** 2) + ($dy ** 2) + ($dz ** 2));
		if ($distance < self::ARRIVAL_DISTANCE) {
			$this->mob->getMovementInput()->walk(0);
			return;
		}

		$wantedYaw = (atan2($dz, $dx) * 180 / M_PI) - 90;
		$yaw = $this->rotateLerp($location->yaw, $wantedYaw, $this->maxYawChange);

		if ($this->swimming) {
			$this->swim($yaw, abs(Utils::wrapDegrees($wantedYaw - $location->yaw)), $location->pitch, $dx, $dy, $dz, $distance);
			if (!$this->mob->getWorld()->getBlock($this->wantedPosition) instanceof Water) {
				// Leaving the water, swimming alone doesn't climb out of a pool
				$this->jumpIfNeeded($dx, $dy, $dz);
			}
		} else {
			$this->mob->setRotation($yaw, $location->pitch);
			$this->mob->getMovementInput()->walk($this->speedModifier * $this->mob->getMovementSpeed() * $this->landSpeed);
			$this->jumpIfNeeded($dx, $dy, $dz);
		}
	}

	private static function getTurnPenalty(float $turnLeft) : float {
		return 1 - Utils::clamp(($turnLeft - self::FULL_SPEED_TURN) / self::STOP_TURN_RANGE, 0, 1);
	}

	/**
	 * The pitch only turns for show, and the mob waits to face the position before pushing sideways.
	 *
	 * @param float $yawError degrees the yaw is off the wanted position by
	 */
	private function swim(float $yaw, float $yawError, float $pitch, float $dx, float $dy, float $dz, float $distance) : void {
		$speed = $this->getSwimSpeed();
		$this->mob->getMovementInput()->walk($speed);

		$distanceXZ = sqrt(($dx ** 2) + ($dz ** 2));
		if (abs($dy) > self::EPSILON || $distanceXZ > self::EPSILON) {
			$wantedPitch = Utils::clamp(
				Utils::wrapDegrees(-atan2($dy, $distanceXZ) * 180 / M_PI),
				-$this->maxPitchChange,
				$this->maxPitchChange
			);
			$pitch = $this->rotateLerp($pitch, $wantedPitch, self::PITCH_STEP);
		}
		$this->mob->setRotation($yaw, $pitch);

		$this->mob->getMovementInput()->setForward($speed * ($distanceXZ / $distance) * self::getTurnPenalty($yawError));
		$this->mob->getMovementInput()->setUpward($speed * $this->getVerticalShare($dy, $distance));
	}

	/**
	 * The movement input while swimming, which the water speed then scales into acceleration.
	 */
	private function getSwimSpeed() : float {
		$speed = $this->thrust === SwimThrust::SLOPED ? $this->mob->getMovementSpeed() : $this->mob->getWaterMovementSpeed();

		return $this->speedModifier * $speed;
	}

	/**
	 * Part of the swim speed spent going up (positive) or down.
	 */
	private function getVerticalShare(float $dy, float $distance) : float {
		if ($this->thrust === SwimThrust::SLOPED) {
			return $dy / $distance;
		}
		if ($this->thrust === SwimThrust::EASED) {
			//The input is scaled by the water speed later, the push is not
			return self::GENTLE_VERTICAL_PUSH * $dy / ($distance * $this->mob->getWaterMovementSpeed());
		}
		$share = max(abs($dy) / (2 * $distance), min(self::VERTICAL_GAIN * abs($dy), self::MAX_VERTICAL_SHARE));
		return $dy < 0 ? -$share : $share;
	}
}
