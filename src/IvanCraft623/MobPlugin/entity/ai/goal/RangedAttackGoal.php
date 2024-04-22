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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\Entity;

use function floor;
use function sqrt;

class RangedAttackGoal extends Goal {

	protected Entity $target;

	protected float $attackRadiusSquared;

	protected int $seeTime = 0;
	protected int $attackTime = -1;

	public function __construct(
		protected Mob $mob,
		protected float $speedModifier,
		protected int $minAttackInterval,
		protected int $maxAttackInterval,
		protected float $attackRadius
	) {
		$this->attackRadiusSquared = $attackRadius ** 2;

		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		$target = $this->mob->getTargetEntity();
		if ($target === null) {
			return false;
		}
		if (!$target->isAlive()) {
			return false;
		}

		$this->target = $target;

		return true;
	}

	public function canContinueToUse() : bool{
		return $this->canUse() || $this->target->isAlive() && !$this->mob->getNavigation()->isDone();
	}

	public function stop() : void{
		unset($this->target);

		$this->seeTime = 0;
		$this->attackTime = -1;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$distanceSqr = $this->mob->getPosition()->distanceSquared($this->target->getPosition());
		$canSee = $this->mob->getSensing()->canSee($this->target);
		if ($canSee) {
			$this->seeTime++;
		} else {
			$this->seeTime = 0;
		}

		if (!($distanceSqr > $this->attackRadiusSquared) && $this->seeTime >= 5) {
			$this->mob->getNavigation()->stop();
		} else {
			$this->mob->getNavigation()->moveToEntity($this->target, $this->speedModifier);
		}

		$this->mob->getLookControl()->setLookAt($this->target, 30, 30);

		if (--$this->attackTime === 0) {
			if (!$canSee) {
				return;
			}

			$distancePercentage = sqrt($distanceSqr) / $this->attackRadius;
			$this->mob->performRangedAttack($this->target, Utils::clamp($distancePercentage, 0.1, 1));

			$this->attackTime = (int) floor($distancePercentage * ($this->maxAttackInterval - $this->minAttackInterval) + $this->minAttackInterval);
		} elseif ($this->attackTime < 0) {
			$this->attackTime = (int) floor(Utils::lerp(
				sqrt($distanceSqr) / $this->attackRadius, $this->minAttackInterval, $this->maxAttackInterval
			));
		}
	}
}
