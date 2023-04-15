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

use pocketmine\block\Door;
use pocketmine\block\Fence;
use pocketmine\math\Vector3;
use function atan2;
use function cos;
use function floor;
use function max;
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
			$z = $strafeRight * $cos - $strafeForwards * $sin;
			if (!$this->isWalkable($x, $z)) {
				$this->strafeForwards = 1;
				$this->strafeRight = 0;
			}
			$this->mob->setZza($this->strafeForwards);
			$this->mob->setXxa($this->strafeRight);
			$this->operation = self::OPERATION_WAIT;
		} elseif ($this->operation === self::OPERATION_MOVE_TO) {
			$this->operation = self::OPERATION_WAIT;
			$x = $this->wantedPosition->x - $location->x;
			$y = $this->wantedPosition->y - $location->y;
			$z = $this->wantedPosition->z - $location->z;
			$distanceSquared = ($x ** 2) + ($y ** 2) + ($z ** 2);
			if ($distanceSquared < (2.5 * 10 ** -7)) {
				$this->mob->setZza(0);
				return;
			}
			$yaw = $this->rotateLerp($location->yaw, (atan2($z, $x) * (180 / M_PI)) - 90, 90);
			$this->mob->setRotation($yaw, 0.0);
			$this->mob->setSpeed($this->speedModifier * $this->mob->getDefaultSpeed());

			$motion = $this->mob->getMotion();
			foreach ($location->getWorld()->getCollisionBlocks($this->mob->boundingBox->addCoord($motion->x, $motion->y, $motion->z)) as $block) {
				if ($block->getCollisionBoxes()[0]->maxY - $this->mob->boundingBox->minY > 1) {
					if ($y > $this->mob->getMaxUpStep() &&
					($x ** 2) + ($z ** 2) < max(1.0, $this->mob->getSize()->getWidth()) &&
					!$block instanceof Door &&
					!$block instanceof Fence) {
						$this->mob->getJumpControl()->jump();
						$this->operation = self::OPERATION_JUMPING;
						return;
					}
				}
			}
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
