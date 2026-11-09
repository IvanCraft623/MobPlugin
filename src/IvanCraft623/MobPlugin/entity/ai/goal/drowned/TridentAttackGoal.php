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

namespace IvanCraft623\MobPlugin\entity\ai\goal\drowned;

use IvanCraft623\MobPlugin\entity\ai\goal\RangedAttackGoal;
use IvanCraft623\MobPlugin\entity\monster\Drowned;

/**
 * Throws the held trident from afar, and leaves the target to melee once it gets close.
 */
class TridentAttackGoal extends RangedAttackGoal{

	private const ATTACK_RADIUS = 10; //blocks
	private const START_DISTANCE_SQUARED = 25; //blocks squared
	private const STOP_DISTANCE_SQUARED = 9; //blocks squared

	public function __construct(private Drowned $drowned){
		parent::__construct($drowned, 1, 20, 60, self::ATTACK_RADIUS);
	}

	public function canUse() : bool{
		return $this->drowned->isHoldingTrident() && parent::canUse() && $this->targetDistanceSquared() >= self::START_DISTANCE_SQUARED;
	}

	public function canContinueToUse() : bool{
		return $this->drowned->isHoldingTrident() && $this->target->isAlive() && $this->targetDistanceSquared() >= self::STOP_DISTANCE_SQUARED;
	}

	private function targetDistanceSquared() : float{
		return $this->drowned->getPosition()->distanceSquared($this->target->getPosition());
	}
}