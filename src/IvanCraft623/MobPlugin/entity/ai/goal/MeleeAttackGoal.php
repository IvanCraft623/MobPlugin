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

use IvanCraft623\MobPlugin\entity\PathfinderMob;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use function max;

/**
 * Custom melee attack goal (NOT a vanilla port).
 *
 * Chases the current target and hits it once it's within attack reach.
 * Re-paths periodically instead of relying on the navigator's internal
 * "done" state.
 */
class MeleeAttackGoal extends Goal {

	public const ATTACK_INTERVAL = 20;

	public const REPATH_INTERVAL = 10;

	private int $ticksToAttack = 0;

	private int $ticksToRepath = 0;

	public function __construct(
		protected PathfinderMob $mob,
		protected float $speedModifier,
		protected bool $alwaysFollowTarget
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return $this->isTargetValid($this->mob->getTargetEntity());
	}

	public function canContinueToUse() : bool{
		return $this->isTargetValid($this->mob->getTargetEntity());
	}

	protected function isTargetValid(?Entity $target) : bool{
		if (!$target instanceof Living || !$target->isAlive()) {
			return false;
		}

		if (!$this->mob->canAttack($target)) {
			return false;
		}

		if (!$this->alwaysFollowTarget && !$this->mob->isWithinRestriction($target->getPosition())) {
			return false;
		}

		if ($target instanceof Player && $target->isCreative()) {
			return false;
		}

		return true;
	}

	public function start() : void{
		$this->mob->setAggressive();
		$this->ticksToAttack = 0;
		$this->ticksToRepath = 0;
	}

	public function stop() : void{
		$this->mob->setAggressive(false);
		$this->mob->getNavigation()->stop();
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if ($target === null) {
			return;
		}

		$this->mob->getLookControl()->setLookAt($target, 30, 30);

		$distanceSquared = $this->mob->getPerceivedDistanceSqrForMeleeAttack($target);
		$attackReachSquared = $this->getAttackReachSquared($target);

		$this->ticksToRepath = max($this->ticksToRepath - 1, 0);
		if ($distanceSquared > $attackReachSquared && $this->ticksToRepath <= 0) {
			$this->mob->getNavigation()->moveToEntity($target, $this->speedModifier, 0);
			$this->ticksToRepath = $this->adjustedTickDelay(self::REPATH_INTERVAL);
		}

		$this->ticksToAttack = max($this->ticksToAttack - 1, 0);
		if ($distanceSquared <= $attackReachSquared && $this->isTimeToAttack()) {
			$this->resetAttackCooldown();
			$this->mob->attackEntity($target);
		}
	}

	public function resetAttackCooldown() : void{
		$this->ticksToAttack = $this->getAttackInterval();
	}

	public function isTimeToAttack() : bool{
		return $this->ticksToAttack <= 0;
	}

	public function getTicksToAttack() : int{
		return $this->ticksToAttack;
	}

	public function getAttackInterval() : int{
		return $this->adjustedTickDelay(self::ATTACK_INTERVAL);
	}

	public function getAttackReachSquared(Entity $target) : float{
		$entityWidth = $this->mob->getSize()->getWidth();
		return $entityWidth * 2 * $entityWidth * 2 + $target->getSize()->getWidth();
	}
}
