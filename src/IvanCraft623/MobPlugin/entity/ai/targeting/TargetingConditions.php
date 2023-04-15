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

use Closure;
use IvanCraft623\MobPlugin\entity\Living;

use IvanCraft623\MobPlugin\entity\Mob;
use pocketmine\entity\Living as PMLiving;
use pocketmine\player\Player;
use function max;

class TargetingConditions {

	/**
	 * @phpstan-param null|Closure(PMLiving) : bool $validator
	 */
	public function __construct(
		protected float $range = -1,
		protected bool $allowInvulnerable = false,
		protected bool $allowUnseeable = false,
		protected bool $allowNonAttackable = false,
		protected bool $testInvisible = false,
		protected ?Closure $validator = null
	) {
	}

	public function setRange(float $range) : self{
		$this->range = $range;
		return $this;
	}

	/**
	 * @phpstan-param null|Closure(PMLiving) : bool $validator
	 */
	public function setValidator(?Closure $validator) : self{
		$this->validator = $validator;
		return $this;
	}

	public function allowInvulnerable(bool $value = true) : self{
		$this->allowInvulnerable = $value;
		return $this;
	}

	public function allowUnseeable(bool $value = true) : self{
		$this->allowUnseeable = $value;
		return $this;
	}

	public function allowNonAttackable(bool $value = true) : self{
		$this->allowNonAttackable = $value;
		return $this;
	}

	public function testInvisible(bool $value = true) : self{
		$this->testInvisible = $value;
		return $this;
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
		if ($this->validator !== null && !($this->validator)($target)) {
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
