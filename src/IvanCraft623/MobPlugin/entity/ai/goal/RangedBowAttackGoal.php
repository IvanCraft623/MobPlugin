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
use IvanCraft623\MobPlugin\entity\RangedAttackMob;

use pocketmine\item\ItemTypeIds;
use function max;
use function min;
use const M_SQRT2;

class RangedBowAttackGoal extends Goal {

	private float $attackRadiusSqr;
	private int $attackCooldown = -1;
	private int $targetVisibilityTicks = 0;
	private bool $strafeLeft = false;
	private bool $moveBackward = false;
	private bool $inCombat = false;
	private int $combatTicks = 0;
	private int $startActionTick = -1;

	public function __construct(
		protected Mob&RangedAttackMob $entity,
		protected float $speedModifier,
		private int $attackInterval,
		float $attackRadius
	) {
		$this->attackRadiusSqr = $attackRadius * $attackRadius;
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function setAttackInterval(int $attackInterval) : void {
		$this->attackInterval = $attackInterval;
	}

	public function canUse() : bool {
		return $this->entity->getTargetEntity() !== null && $this->isHoldingBow();
	}

	protected function isHoldingBow() : bool {
		return $this->entity->getInventory()->getItemInHand()->getTypeId() === ItemTypeIds::BOW;
	}

	public function canContinueToUse() : bool {
		return ($this->canUse() || !$this->entity->getNavigation()->isDone()) && $this->isHoldingBow();
	}

	public function start() : void {
		$this->entity->setAggressive(true);
	}

	public function stop() : void {
		$this->entity->setAggressive(false);
		$this->targetVisibilityTicks = 0;
		$this->attackCooldown = -1;
		$this->inCombat = false;
		$this->combatTicks = 0;
		$this->startActionTick = -1;
	}

	public function requiresUpdateEveryTick() : bool {
		return true;
	}

	public function tick() : void {
		$target = $this->entity->getTargetEntity();
		if ($target === null) {
			return;
		}

		$distanceSqr = $this->entity->getLocation()->distanceSquared($target->getLocation());
		$canSeeTarget = $this->entity->getSensing()->canSee($target);

		if ($canSeeTarget !== ($this->targetVisibilityTicks > 0)) {
			$this->targetVisibilityTicks = 0;
		}

		$this->targetVisibilityTicks = $canSeeTarget
			? min($this->targetVisibilityTicks + 1, 60)
			: max($this->targetVisibilityTicks - 1, -60);

		if ($distanceSqr <= $this->attackRadiusSqr && $this->targetVisibilityTicks >= 20) {
			$this->entity->getNavigation()->stop();
			$this->inCombat = true;
		} else {
			$this->entity->getNavigation()->moveToEntity($target, $this->speedModifier);
			$this->inCombat = false;
			$this->combatTicks = 0;
		}

		if ($this->inCombat) {
			$this->combatTicks++;

			if ($this->combatTicks >= 20) {
				if ($this->entity->getRandom()->nextFloat() < 0.3) {
					$this->strafeLeft = !$this->strafeLeft;
				}
				if ($this->entity->getRandom()->nextFloat() < 0.3) {
					$this->moveBackward = !$this->moveBackward;
				}
				$this->combatTicks = 0;
			}

			if ($distanceSqr > $this->attackRadiusSqr * 0.75) {
				$this->moveBackward = false;
			} elseif ($distanceSqr < $this->attackRadiusSqr * 0.25) {
				$this->moveBackward = true;
			}

			//TODO: Vanilla uses a fixed 0.5 here, but porting it 1:1 made mobs strafe ~4x faster
			// than walking speed. Traced the whole motion pipeline against decompiled Java
			// (MoveControl/LivingEntity/MobEntity) and couldn't find what dampens it there.
			// Using movement_speed, goal_speed_modifier and sneaking_speed_modifier(0.3) as a
			// stand-in until the real mechanism is found.
			$speed = ($this->entity->getMovementSpeed() * $this->speedModifier * 0.3) / M_SQRT2;
			$this->entity->getMoveControl()->strafe(
				$this->moveBackward ? -$speed : $speed,
				$this->strafeLeft ? $speed : -$speed
			);
		}

		// TODO: handle controlling vehicle, when this entity rides another Mob,
		// the vehicle should also look at the target.
		$this->entity->getLookControl()->setLookAt($target, 30, 30);

		if ($this->startActionTick > -1) {
			$useTicks = $this->entity->getWorld()->getServer()->getTick() - $this->startActionTick;

			if (!$canSeeTarget && $this->targetVisibilityTicks <= -60) {
				$this->startActionTick = -1;
			} elseif ($canSeeTarget) {
				if ($useTicks >= 20) {
					$this->startActionTick = -1;
					$pullProgress = min($useTicks / 20.0, 1.0);
					$this->entity->performRangedAttack($target, $pullProgress);
					$this->attackCooldown = $this->attackInterval;
				}
			}
		} elseif (--$this->attackCooldown <= 0 && $this->targetVisibilityTicks >= -60) {
			$this->startActionTick = $this->entity->getWorld()->getServer()->getTick();
		}
	}
}
