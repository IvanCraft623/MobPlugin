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

use IvanCraft623\MobPlugin\entity\ai\navigation\locomotion\Locomotion;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\math\Vector3;
use function max;

/**
 * Drives a mob toward what its goals want. How it does so depends on how the mob moves: see the subclasses.
 */
abstract class MoveControl implements Control {

	protected Vector3 $wantedPosition;

	protected float $speedModifier;

	protected float $forwardMovement;

	protected float $sidewaysMovement;

	protected MoveOperation $operation = MoveOperation::WAIT;

	public function __construct(protected Mob $mob) {
	}

	public function hasWanted() : bool {
		return $this->operation === MoveOperation::MOVE_TO;
	}

	public function getSpeedModifier() : float {
		return $this->speedModifier;
	}

	public function setWantedPosition(Vector3 $position, float $speedModifier) : void {
		$this->wantedPosition = $position;
		$this->speedModifier = $speedModifier;
		if ($this->operation !== MoveOperation::JUMPING) {
			$this->operation = MoveOperation::MOVE_TO;
		}
	}

	/**
	 * Asks to move sideways or backwards while keeping the look direction. Controls that can't strafe ignore it.
	 */
	public function strafe(float $forwardMovement, float $sidewaysMovement) : void {
		$this->operation = MoveOperation::STRAFE;
		$this->forwardMovement = $forwardMovement;
		$this->sidewaysMovement = $sidewaysMovement;
		$this->speedModifier = 1 / 4;
	}

	abstract public function tick() : void;

	/**
	 * Jumps when the wanted position is more than a step up and close.
	 */
	protected function jumpIfNeeded(float $dx, float $dy, float $dz) : void {
		if ($dy > $this->mob->getStepHeight() && ($dx ** 2) + ($dz ** 2) < max(1.0, $this->mob->getSize()->getWidth())) {
			$this->mob->getJumpControl()->jump();
			$this->operation = MoveOperation::JUMPING;
		}
	}

	protected function tickJumping() : void {
		$this->mob->getMovementInput()->walk($this->speedModifier * $this->mob->getMovementSpeed());
		if ($this->mob->onGround || Locomotion::isInLiquid($this->mob)) {
			$this->operation = MoveOperation::WAIT;
		}
	}

	protected function rotateLerp(float $startDegrees, float $endDegrees, float $maxRotation) : float {
		$delta = Utils::clamp(Utils::wrapDegrees($endDegrees - $startDegrees), -$maxRotation, $maxRotation);
		return Utils::wrapDegrees($startDegrees + $delta);
	}

	public function getWantedPosition() : Vector3 {
		return $this->wantedPosition;
	}
}
