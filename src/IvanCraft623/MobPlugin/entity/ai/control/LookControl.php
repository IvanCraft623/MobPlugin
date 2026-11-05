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

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use function abs;
use function atan2;
use function sqrt;
use const M_PI;

/**
 * Turns the head and pitch toward a look target. The body turns with the movement; Living::lookAt() with limits
 * turns it too.
 */
class LookControl implements Control {

	/** Degrees per tick the head turns back to the body when there is nothing to look at */
	private const HEAD_RECENTER_SPEED = 10.0;

	/** Horizontal motion squared above which the mob counts as moving */
	private const MOVING_MOTION_SQUARED = 2.5E-7;

	protected Mob $mob;

	protected float $yawMaxRotationAngle;

	protected float $pitchMaxRotationAngle;

	protected int $lookAtTimer = 0;

	protected Vector3 $wanted;

	protected bool $resetPitchOnTick = true;

	/**
	 * @param int $rotSpeed    default yaw degrees per tick when looking at something
	 * @param int $maxYawRot   most degrees the head can be away from the body
	 * @param int $maxPitchRot default pitch degrees per tick when looking at something
	 */
	public function __construct(
		Mob $mob,
		private int $rotSpeed = 10,
		private int $maxYawRot = 75,
		private int $maxPitchRot = 40
	) {
		$this->mob = $mob;
	}

	public function getMaxYawRot() : int {
		return $this->maxYawRot;
	}

	public function setLookAt(Entity|Vector3 $lookAt, ?float $yawMaxRotationAngle = null, ?float $pitchMaxRotationAngle = null) : void {
		$this->wanted = $lookAt instanceof Entity ? $lookAt->getEyePos() : $lookAt;
		$this->yawMaxRotationAngle = $yawMaxRotationAngle ?? $this->rotSpeed;
		$this->pitchMaxRotationAngle = $pitchMaxRotationAngle ?? $this->maxPitchRot;
		$this->lookAtTimer = 2;
	}

	public function tick() : void {
		$location = $this->mob->getLocation();
		$pitch = $this->resetPitchOnTick() ? 0.0 : $location->pitch;
		$headYaw = $this->mob->getHeadYaw();

		if ($this->lookAtTimer > 0) {
			$this->lookAtTimer--;

			$dx = $this->wanted->x - $location->x;
			$dy = $this->wanted->y - ($location->y + $this->mob->getEyeHeight());
			$dz = $this->wanted->z - $location->z;
			$headYaw = static::rotateTowards($headYaw, (atan2($dz, $dx) * (180 / M_PI)) - 90, $this->yawMaxRotationAngle);
			$pitch = static::rotateTowards($pitch, -(atan2($dy, sqrt(($dx ** 2) + ($dz ** 2))) * (180 / M_PI)), $this->pitchMaxRotationAngle);
		} else {
			$headYaw = static::rotateTowards($headYaw, $location->yaw, self::HEAD_RECENTER_SPEED);
		}

		//The head can't turn too far from the body: it drags the body along when standing, and stops at the limit when moving
		$bodyYaw = $location->yaw;
		if (abs(Utils::degreesDifference($bodyYaw, $headYaw)) > $this->maxYawRot) {
			$motion = $this->mob->getMotion();
			if (($motion->x ** 2) + ($motion->z ** 2) > self::MOVING_MOTION_SQUARED) {
				$headYaw = Utils::rotateIfNecessary($headYaw, $bodyYaw, $this->maxYawRot);
			} else {
				$bodyYaw = Utils::rotateIfNecessary($bodyYaw, $headYaw, $this->maxYawRot);
			}
		}

		$this->mob->setHeadYaw($headYaw);
		if ($bodyYaw !== $location->yaw || $pitch !== $location->pitch) {
			$this->mob->setRotation($bodyYaw, $pitch);
		}
	}

	public function resetPitchOnTick() : bool {
		return $this->resetPitchOnTick;
	}

	public function setResetPitchOnTick(bool $value) : void {
		$this->resetPitchOnTick = $value;
	}

	public function hasWanted() : bool {
		return $this->lookAtTimer > 0;
	}

	public function getWanted() : ?Vector3 {
		return $this->wanted;
	}

	public static function rotateTowards(float $currentDegrees, float $targetDegrees, float $maxRotation) : float {
		$degreesDifference = Utils::degreesDifference($currentDegrees, $targetDegrees);
		return $currentDegrees + Utils::clamp($degreesDifference, -$maxRotation, $maxRotation);
	}
}