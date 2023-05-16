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
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\math\Vector3;
use function atan2;
use function cos;
use function floor;
use function sin;
use function sqrt;
use const M_PI;

class MoveControl implements Control {

	public const OPERATION_WAIT = 0;
	public const OPERATION_MOVE_TO = 1;
	public const OPERATION_STRAFE = 2;
	public const OPERATION_JUMPING = 3;

	protected Mob $mob;

	protected Vector3 $wantedPosition;

	protected float $speedModifier;

	protected float $strafeForwards;

	protected float $strafeRight;

	protected int $operation = self::OPERATION_WAIT;

	public function __construct(Mob $mob) {
		$this->mob = $mob;
	}

	public function hasWanted() : bool {
		return $this->operation === self::OPERATION_MOVE_TO;
	}

	public function getSpeedModifier() : float {
		return $this->speedModifier;
	}

	public function setWantedPosition(Vector3 $position, float $speedModifier) : void {
		$this->wantedPosition = $position;
		$this->speedModifier = $speedModifier;
		if ($this->operation !== self::OPERATION_JUMPING) {
			$this->operation = self::OPERATION_MOVE_TO;
		}
	}

	public function strafe(float $strafeForwards, float $strafeRight) : void {
		$this->operation = self::OPERATION_STRAFE;
		$this->strafeForwards = $strafeForwards;
		$this->strafeRight = $strafeRight;
		$this->speedModifier = 1 / 4;
	}

	public function tick() : void {
		$location = $this->mob->getLocation();
		if ($this->operation === self::OPERATION_STRAFE) {
			$speed = $this->speedModifier * $this->mob->getDefaultSpeed();
			$strafeForwards = $this->strafeForwards;
			$strafeRight = $this->strafeRight;
			$strafe = sqrt(($strafeForwards ** 2) + ($strafeRight ** 2));
			if ($strafe < 1) {
				$strafe = 1;
			}
			$strafe = $speed / $strafe;
			$strafeForwards *= $strafe;
			$strafeRight *= $strafe;

			$sin = sin($location->yaw * (M_PI / 180));
			$cos = cos($location->yaw * (M_PI / 180));

			$x = $strafeForwards * $cos - $strafeRight * $sin;
			$z = $strafeRight * $cos + $strafeForwards * $sin;
			if (!$this->isWalkable($x, $z)) {
				$this->strafeForwards = 1;
				$this->strafeRight = 0;
			}

			$this->mob->setSpeed($speed);
			$this->mob->setZza($this->strafeForwards);
			$this->mob->setXxa($this->strafeRight);

			$this->operation = self::OPERATION_WAIT;
		} elseif ($this->operation === self::OPERATION_MOVE_TO) {
			$this->operation = self::OPERATION_WAIT;

			$dx = $this->wantedPosition->x - $location->x;
			$dy = $this->wantedPosition->y - $location->y;
			$dz = $this->wantedPosition->z - $location->z;
			$distanceSquared = ($dx ** 2) + ($dy ** 2) + ($dz ** 2);

			if ($distanceSquared < 2.5E-7) { // 0.0005 ** 2
				$this->mob->setZza(0);
				return;
			}

			$yaw = $this->rotateLerp($location->yaw, (atan2($dz, $dx) * 180 / M_PI) - 90, 90);
			$this->mob->setRotation($yaw, $location->pitch);
			$this->mob->setSpeed($this->speedModifier * $this->mob->getDefaultSpeed());

			$block = $this->mob->getWorld()->getBlock($location);
			//TODO: jump check logic
		} elseif ($this->operation === self::OPERATION_JUMPING) {
			$this->mob->setSpeed($this->speedModifier * $this->mob->getDefaultSpeed());
			if ($this->mob->onGround) {
				$this->operation = self::OPERATION_WAIT;
			}
		} else {
			$this->mob->setZza(0);
		}
	}

	private function isWalkable(float $x, float $z) : bool {
		$navigation = $this->mob->getNavigation();
		if ($navigation !== null) {
			$nodeEvaluator = $navigation->getNodeEvaluator();
			$location = $this->mob->getLocation();
			if ($nodeEvaluator !== null &&
				!$nodeEvaluator->getBlockPathType($this->mob->getWorld(), (int) floor($location->x + $x), (int) floor($location->y), (int) floor($location->z + $z))->equals(BlockPathTypes::WALKABLE())) {
				return false;
			}
		}
		return true;
	}

	protected function rotateLerp(float $startDegrees, float $endDegrees, float $maxRotation) : float {
		$delta = Utils::clamp(Utils::wrapDegrees($endDegrees - $startDegrees), -$maxRotation, $maxRotation);
		return Utils::wrapDegrees($startDegrees + $delta);
	}

	public function getWantedPosition() : Vector3 {
		return $this->wantedPosition;
	}
}
