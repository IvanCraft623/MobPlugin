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

namespace IvanCraft623\MobPlugin\entity\monster\slime;

use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use const M_PI;

class SlimeMoveControl extends MoveControl{

	protected float $yaw;

	protected int $jumpDelay = 0;

	protected bool $isAggresive = false;

	public function __construct(protected Slime $slime) {
		$this->yaw = 180 * $slime->getLocation()->getYaw() / M_PI;

		parent::__construct($slime);
	}

	public function setDirection(float $yaw, bool $aggresive) : void{
		$this->yaw = $yaw;
		$this->isAggresive = $aggresive;
	}

	public function setWantedMovement(float $speedModifier) : void{
		$this->speedModifier = $speedModifier;
		$this->operation = MoveControl::OPERATION_MOVE_TO;
	}

	public function tick() : void {
		$location = $this->mob->getLocation();

		$this->mob->setRotation($this->rotateLerp($location->yaw, $this->yaw, 90), $location->pitch);

		if ($this->operation === MoveControl::OPERATION_MOVE_TO) {
			$this->operation = MoveControl::OPERATION_WAIT;
			if ($this->mob->isOnGround()) {
				if (--$this->jumpDelay <= 0) {
					$this->jumpDelay = $this->slime->getJumpDelay();
					if ($this->isAggresive) {
						$this->jumpDelay = (int) ($this->jumpDelay / 3);
					}

					$this->mob->setForwardSpeed($this->speedModifier * $this->mob->getDefaultMovementSpeed());
					$this->slime->getJumpControl()->jump();
				}
			} else {
				$this->mob->setForwardSpeed(0);
				$this->mob->setSidewaysSpeed(0);
			}
		} else {
			$this->mob->setForwardSpeed(0);
		}
	}
}
