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

namespace IvanCraft623\MobPlugin\entity\animal\rabbit;

use IvanCraft623\MobPlugin\entity\ai\control\GroundMoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveOperation;

/**
 * Moves in hops: stands still on the ground between them and moves forward in the air, at a speed that is
 * fixed when the hop starts.
 */
class RabbitMoveControl extends GroundMoveControl {

	private const HOP_DELAY = 15; //ticks
	private const FAST_HOP_DELAY = 1; //ticks
	private const FAST_HOP_THRESHOLD = 0.8; //speed modifier above which the hops are fast
	private const HOP_SPEED_MODIFIER = 1.2; //times the movement speed
	private const FAST_HOP_SPEED_MODIFIER = 2.625; //times the movement speed
	private const HOP_VELOCITY = 0.25; //blocks per tick
	private const FAST_HOP_VELOCITY = 0.15; //blocks per tick

	private int $hopDelay = 0;

	private float $hopSpeedModifier = 0.0;

	protected function drive() : void {
		if ($this->mob->isOnGround()) {
			$this->hopSpeedModifier = 0.0;
		} elseif ($this->hopSpeedModifier > 0.0 && $this->operation === MoveOperation::MOVE_TO) {
			$this->speedModifier = $this->hopSpeedModifier;
		}

		$wasMoving = $this->operation === MoveOperation::MOVE_TO;

		parent::drive();

		if (!$wasMoving || $this->operation === MoveOperation::JUMPING || !$this->mob->isOnGround()) {
			return;
		}

		if ($this->hopDelay > 0) {
			$this->hopDelay--;
			$this->mob->getMovementInput()->setForward(0);
			return;
		}

		$fast = $this->speedModifier > self::FAST_HOP_THRESHOLD;
		$this->hopDelay = $fast ? self::FAST_HOP_DELAY : self::HOP_DELAY;
		$this->hopSpeedModifier = $fast ? self::FAST_HOP_SPEED_MODIFIER : self::HOP_SPEED_MODIFIER;
		$this->mob->getMovementInput()->walk($this->hopSpeedModifier * $this->mob->getMovementSpeed());
		$this->mob->setMotion($this->mob->getMotion()->withComponents(null, $fast ? self::FAST_HOP_VELOCITY : self::HOP_VELOCITY, null));
	}
}
