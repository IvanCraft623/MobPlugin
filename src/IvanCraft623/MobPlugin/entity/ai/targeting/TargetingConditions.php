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

namespace IvanCraft623\MobPlugin\entity\ai\targeting;

use IvanCraft623\MobPlugin\entity\Living;
use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\entity\Living as PMLiving;
use pocketmine\player\Player;
use function max;

class TargetingConditions {

	public float $range = -1;

	public bool $allowInvulnerable;

	public bool $allowUnseeable;

	public bool $allowNonAttackable;

	public bool $testInvisible = true;

	public function __construct(float $range, bool $allowInvulnerable, bool $allowUnseeable, bool $allowNonAttackable, bool $testInvisible) {
		$this->range = $range;
		$this->allowInvulnerable = $allowInvulnerable;
		$this->allowUnseeable = $allowUnseeable;
		$this->allowNonAttackable = $allowNonAttackable;
		$this->testInvisible = $testInvisible;
	}

	public function test(?PMLiving $entity, PMLiving $target) : bool {
		if ($entity === $target) {
			return false;
		}
		if ($target instanceof Player && $target->isSpectator()) {
			return false;
		}
		if (!$target->isAlive()) {
			return false;
		}
		if (!$this->allowInvulnerable && ($target instanceof Player && $target->isCreative())) {
			return false;
		}
		if ($entity !== null) {
			if (!$this->allowNonAttackable) {
				if ($entity instanceof Living && !$entity->canAttack($target)) {
					return false;
				}
			}
			if ($this->range > 0) {
				$percent = $this->testInvisible ? TargetingUtils::getVisibilityPercent($target, $entity) : 1.0;
				$visibility = max($this->range * $percent, 2.0);
				$distanceSquare = $entity->getLocation()->distanceSquared($target->getLocation());
				if ($distanceSquare > $visibility * $visibility) {
					return false;
				}
			}
			if (!$this->allowUnseeable && $entity instanceof Mob && !$entity->getSensing()->canSee($target)) {
				return false;
			}
		}
		return true;
	}
}
