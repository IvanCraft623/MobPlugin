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

class LookControl {

	protected Mob $mob;

	protected float $yawMaxRotationSpeed;

	protected float $pitchMaxRotationAngle;

	protected bool $hasWanted = false;

	protected ?Vector3 $wanted = null;

	public function __construct(Mob $mob) {
		$this->mob = $mob;
	}

	public function setLookAt(Entity|Vector3 $lookAt, ?float $yawMaxRotationSpeed = null, ?float $pitchMaxRotationAngle = null) : void {
		$this->wanted = $lookAt instanceof Entity ? $lookAt->getEyePos() : $lookAt;
		$this->yawMaxRotationSpeed = $yawMaxRotationSpeed ?? $this->mob->getRotSpeed();
		$this->pitchMaxRotationAngle = $pitchMaxRotationAngle ?? $this->mob->getMaxPitchRot();
		$this->hasWanted = true;
	}

	public function tick() : void {
		$location = $this->mob->getLocation();
		if ($this->resetPitchOnTick()) {
			$this->mob->setRotation($location->yaw, 0.0);
		}
		if ($this->hasWanted) {
			$this->hasWanted = false;
			$yaw = $this->rotateTowards($location->yaw, $this->getYawD(), $this->yawMaxRotationSpeed);
			$pitch = $this->rotateTowards($location->pitch, $this->getPitchD(), $this->pitchMaxRotationAngle);
			$this->mob->setRotation($yaw, $pitch);
		} else {
			$this->mob->setRotation($this->rotateTowards($location->yaw, $location->yaw, 10.0), 0.0);
		}
		if (!$this->mob->getNavigation()->isDone()) {
			$this->mob->setRotation(Utils::rotateIfNecessary($location->yaw, $location->yaw, $this->mob->getMaxYawRot()), 0.0);
		}
	}

	protected function resetPitchOnTick() : bool {
		return true;
	}

	public function hasWanted() : bool {
		return $this->hasWanted;
	}

	public function getWanted() : ?Vector3 {
		return $this->wanted;
	}

	public function getPitchD() : float {
		$diff = $this->wanted->subtract($this->mob->getEyePos());
		return -(atan2($diff->y, sqrt(($diff->x ** 2) + ($diff->z ** 2))) * (180 / M_PI));
	}

	public function getYawD() : float {
		$diff = $this->wanted->subtract($this->mob->getEyePos());
		return (atan2($diff->z, $diff->x) * (180 / M_PI)) - 90;
	}

	protected function rotateTowards(float $currentDegrees, float $targetDegrees, float $maxRotation) : float {
		return Utils::degreesDifference($currentDegrees, $targetDegrees) + Utils::clamp($degreesDifference, -$maxRotation, $maxRotation);
	}
}
