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
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function max;

class MeleeAttackGoal extends Goal {

	public const CAN_USE_COOLDOWN = 20;

	private Vector3 $lastTargetPosition;

	private int $ticksToRecalculatePath = 0;

	private int $ticksToAttack = 0;

	private int $attackInterval = 20;

	private int $lastCanUseCheck = 0;

	public function __construct(
		protected PathfinderMob $mob,
		protected float $speedModifier,
		protected bool $alwaysFollowTarget
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		$time = $this->mob->getWorld()->getServer()->getTick();
		if ($time - $this->lastCanUseCheck < self::CAN_USE_COOLDOWN) {
			return false;
		}

		$this->lastCanUseCheck = $time;

		$target = $this->mob->getTargetEntity();
		if ($target === null || !$target->isAlive()) {
			return false;
		}

		$this->path = $this->mob->getNavigation()->createPath($target, 0);
		if ($this->path !== null) {
			return true;
		}
		return $this->getAttackReachSquared($target) >= $this->mob->getLocation()->distanceSquared($target->getLocation());
	}

	public function canContinueToUse() : bool{
		$target = $this->mob->getTargetEntity();
		if ($target === null || !$target->isAlive()) {
			return false;
		}

		if (!$this->alwaysFollowTarget) {
			return !$this->mob->getNavigation()->isDone();
		}
		if (!$this->mob->isWithinRestriction($target->getPosition())) {
			return false;
		}
		if ($target instanceof Player) {
			return !$target->isCreative();
		}

		return true;
	}

	public function start() : void{
		$this->mob->getNavigation()->moveToPath($this->path, $this->speedModifier);
		$this->mob->setAggressive();

		$this->ticksToRecalculatePath = 0;
		$this->ticksToAttack = 0;
	}

	public function stop() : void{
		$target = $this->mob->getTargetEntity();
		if ($target instanceof Player && $target->isCreative()) {
			$this->mob->setTargetEntity(null);
		}

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

		$distSqr = $this->mob->getPerceivedDistanceSqrForMeleeAttack($target);
		$this->ticksToRecalculatePath = max($this->ticksToRecalculatePath - 1, 0);

		if (($this->alwaysFollowTarget || $this->mob->getSensing()->canSee($target)) &&
			$this->ticksToRecalculatePath <= 0 &&
			(
				!isset($this->lastTargetPosition) ||
				$target->getPosition()->distanceSquared($this->lastTargetPosition) >= 1 ||
				$this->mob->getRandom()->nextFloat() < 0.05
			)
		) {
			$this->lastTargetPosition = $target->getPosition();
			$this->ticksToRecalculatePath = 4 + $this->mob->getRandom()->nextInt(7);

			if ($distSqr > 1024) { // 32 ** 2
				$this->ticksToRecalculatePath += 10;
			} elseif ($distSqr > 256) { // 16 ** 2
				$this->ticksToRecalculatePath += 5;
			}

			if (!$this->mob->getNavigation()->moveToEntity($target, $this->speedModifier)) {
				$this->ticksToRecalculatePath += 15;
			}

			$this->ticksToRecalculatePath = $this->adjustedTickDelay($this->ticksToRecalculatePath);
		}

		$this->ticksToAttack = max($this->ticksToAttack - 1, 0);
		$this->checkAndPerformAttack($target, $distSqr);
	}

	protected function checkAndPerformAttack(Entity $target, float $distanceSquared) : void{
		if ($distanceSquared <= $this->getAttackReachSquared() && $this->isTimeToAttack()) {
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
		return $this->adjustedTickDelay(20);
	}

	public function getAttackReachSquared(Entity $target) : float{
		$entityWidth = $this->mob->getSize()->getWidth();
		return $entityWidth * 2 * $entityWidth * 2 + $target->getSize()->getWidth();
	}
}
