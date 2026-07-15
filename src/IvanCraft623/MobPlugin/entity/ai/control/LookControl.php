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
use function atan2;
use function sqrt;
use const M_PI;

class LookControl implements Control {

	protected Mob $mob;

	protected float $yawMaxRotationAngle;

	protected float $pitchMaxRotationAngle;

	protected int $lookAtTimer = 0;

	protected Vector3 $wanted;

	protected bool $resetPitchOnTick = true;

	public function __construct(Mob $mob) {
		$this->mob = $mob;
	}

	public function setLookAt(Entity|Vector3 $lookAt, ?float $yawMaxRotationAngle = null, ?float $pitchMaxRotationAngle = null) : void {
		$this->wanted = $lookAt instanceof Entity ? $lookAt->getEyePos() : $lookAt;
		$this->yawMaxRotationAngle = $yawMaxRotationAngle ?? $this->mob->getRotSpeed();
		$this->pitchMaxRotationAngle = $pitchMaxRotationAngle ?? $this->mob->getMaxPitchRot();
		$this->lookAtTimer = 2;
	}

	public function tick() : void {
		if ($this->resetPitchOnTick()) {
			$this->mob->setRotation($this->mob->getLocation()->yaw, 0.0);
		}

		$location = $this->mob->getLocation();
		if ($this->lookAtTimer > 0) {
			$this->lookAtTimer--;
			$this->mob->setRotation(
				static::rotateTowards($location->yaw, $this->getYawD(), $this->yawMaxRotationAngle),
				static::rotateTowards($location->pitch, $this->getPitchD(), $this->pitchMaxRotationAngle)
			);
		}/* else {
			$this->mob->setRotation(static::rotateTowards($location->yaw, $location->bodyYaw, 10.0), $location->pitch);
		}
		if (!$this->mob->getNavigation()->isDone()) {
			$this->mob->setRotation(Utils::rotateIfNecessary($location->yaw, $location->bodyYaw, $this->mob->getMaxYawRot()), $location->pitch);
		}*/
		//TODO: Body yaw rotation!!!
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

	public function getPitchD() : float {
		$diff = $this->wanted->subtractVector($this->mob->getEyePos());
		return -(atan2($diff->y, sqrt(($diff->x ** 2) + ($diff->z ** 2))) * (180 / M_PI));
	}

	public function getYawD() : float {
		$diff = $this->wanted->subtractVector($this->mob->getEyePos());
		return (atan2($diff->z, $diff->x) * (180 / M_PI)) - 90;
	}

	public static function rotateTowards(float $currentDegrees, float $targetDegrees, float $maxRotation) : float {
		$degreesDifference = Utils::degreesDifference($currentDegrees, $targetDegrees);
		return $currentDegrees + Utils::clamp($degreesDifference, -$maxRotation, $maxRotation);
	}
}
