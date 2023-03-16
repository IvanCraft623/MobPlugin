<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\memory;

use IvanCraft623\MobPlugin\entity\ai\behavior\PositionTracker;

class WalkTarget {

	private PositionTracker $target;

	private float $speedModifier;

	private int $closeEnoughDist;

	public function __construct(PositionTracker $target, float $speedModifier, int $closeEnoughDist) {
		$this->target = $target;
		$this->speedModifier = $speedModifier;
		$this->closeEnoughDist = $closeEnoughDist;
	}

	public function getTarget(): PositionTracker {
		return $this->target;
	}

	public function getSpeedModifier(): float {
		return $this->speedModifier;
	}

	public function getCloseEnoughDist(): int {
		return $this->closeEnoughDist;
	}
}