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

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\Water;

use function abs;
use function atan2;
use function sqrt;
use const M_PI;

class AquaticMoveControl extends MoveControl {

	private const BUOYANCY = 0.005;
	private const PITCH_STEP = 15.0;
	private const ARRIVAL_DISTANCE = 0.0005;
	private const EPSILON = 1.0E-5;
	/** Degrees of turn left below which the mob still swims at full speed */
	private const FULL_SPEED_TURN = 10.0;
	/** Degrees of turn left, past the full speed ones, that slow it down to a stop */
	private const STOP_TURN_RANGE = 50.0;

	/**
	 * @param float $waterSpeed multiplier of the speed while in water
	 * @param float $landSpeed  multiplier of the speed while out of water
	 * @param bool  $buoyant    whether water pushes the mob up
	 */
	public function __construct(
		Mob $mob,
		private int $maxPitchChange,
		private int $maxYawChange,
		private float $waterSpeed,
		private float $landSpeed,
		private bool $buoyant
	) {
		parent::__construct($mob);
	}

	public function tick() : void {
		$inWater = $this->mob->isInWater();
		$this->mob->setHasGravity(!$inWater);
		if ($this->buoyant && $inWater) {
			$this->mob->addMotion(0, self::BUOYANCY, 0);
		}

		$this->mob->setUpwardSpeed(0);

		match ($this->operation) {
			MoveOperation::MOVE_TO => $this->tickMoveTo($inWater),
			MoveOperation::JUMPING => $this->tickJumping(),
			MoveOperation::WAIT,
			MoveOperation::STRAFE => $this->idle(),
		};
	}

	private function idle() : void {
		$this->operation = MoveOperation::WAIT;
		$this->mob->setMotionSpeed(0);
	}

	private function tickMoveTo(bool $inWater) : void {
		if ($this->mob->getNavigation()->isDone()) {
			$this->idle();
			return;
		}

		$this->operation = MoveOperation::WAIT;

		$location = $this->mob->getLocation();
		$dx = $this->wantedPosition->x - $location->x;
		$dy = $this->wantedPosition->y - $location->y;
		$dz = $this->wantedPosition->z - $location->z;
		$distance = sqrt(($dx ** 2) + ($dy ** 2) + ($dz ** 2));
		if ($distance < self::ARRIVAL_DISTANCE) {
			$this->mob->setMotionSpeed(0);
			return;
		}

		$wantedYaw = (atan2($dz, $dx) * 180 / M_PI) - 90;
		$yaw = $this->rotateLerp($location->yaw, $wantedYaw, $this->maxYawChange);

		if ($inWater) {
			$this->swim($yaw, abs(Utils::wrapDegrees($wantedYaw - $location->yaw)), $location->pitch, $dx, $dy, $dz, $distance);
			if (!$this->mob->getWorld()->getBlock($this->wantedPosition) instanceof Water) {
				// Leaving the water, swimming alone doesn't climb out of a pool
				$this->jumpIfNeeded($dx, $dy, $dz);
			}
		} else {
			$this->mob->setRotation($yaw, $location->pitch);
			$this->mob->setMotionSpeed($this->speedModifier * $this->mob->getMovementSpeed() * $this->landSpeed);
			$this->jumpIfNeeded($dx, $dy, $dz);
		}
	}

	/**
	 * 1 for a small turn left, falling to 0 for a big one.
	 */
	private static function getTurnPenalty(float $turnLeft) : float {
		return 1 - Utils::clamp(($turnLeft - self::FULL_SPEED_TURN) / self::STOP_TURN_RANGE, 0, 1);
	}

	/**
	 * Swims straight at the wanted position. The pitch only turns towards it for show, or the
	 * mob would crawl through the turn of a climb. It waits to face the position before it
	 * pushes sideways, so it doesn't circle around a target it hasn't turned to.
	 *
	 * @param float $yawError degrees the yaw is off the wanted position by
	 */
	private function swim(float $yaw, float $yawError, float $pitch, float $dx, float $dy, float $dz, float $distance) : void {
		$speed = $this->speedModifier * $this->mob->getWaterMovementSpeed() * $this->waterSpeed;
		$this->mob->setMotionSpeed($speed);

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

		$this->mob->setForwardSpeed($speed * ($distanceXZ / $distance) * self::getTurnPenalty($yawError));
		$this->mob->setUpwardSpeed($speed * ($dy / $distance));
	}
}