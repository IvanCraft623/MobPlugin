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

namespace IvanCraft623\MobPlugin\entity\ai\memory;

use IvanCraft623\MobPlugin\entity\ai\behavior\PositionTracker;

class WalkTarget {

	private PosTracker $target;

	private float $speedModifier;

	private int $closeEnoughDist;

	public function __construct(PositionTracker $target, float $speedModifier, int $closeEnoughDist) {
		$this->target = $target;
		$this->speedModifier = $speedModifier;
		$this->closeEnoughDist = $closeEnoughDist;
	}

	public function getTarget() : PositionTracker {
		return $this->target;
	}

	public function getSpeedModifier() : float {
		return $this->speedModifier;
	}

	public function getCloseEnoughDist() : int {
		return $this->closeEnoughDist;
	}
}
