<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\control;

use IvanCraft623\MobPlugin\entity\Mob;

class JumpControl {

	protected Mob $mob;

	protected bool $jump = false;

	public function __construct(Mob $mob) {
		$this->mob = $mob;
	}

	public function jump(): void {
		$this->jump = true;
	}

	public function tick(): void {
		$this->mob->jump();
		$this->jump = false;
	}
}