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

namespace IvanCraft623\MobPlugin\entity\ai\goal\slime;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\monster\Slime;

use pocketmine\entity\Living;

class SlimeAttackGoal extends Goal {

	public const DEFAULT_ATTACK_COOLDOWN = 10;

	protected int $growTiredTimer;

	protected int $attackTimer = 0;

	public function __construct(protected Slime $mob, protected int $attackCooldown = self::DEFAULT_ATTACK_COOLDOWN) {
		$this->setFlags(Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		$target = $this->mob->getTargetEntity();
		if (!$target instanceof Living) {
			return false;
		}

		return $this->mob->canAttack($target);
	}

	public function start() : void{
		$this->growTiredTimer = $this->reducedTickDelay(300);

		parent::start();
	}

	public function canContinueToUse() : bool{
		$target = $this->mob->getTargetEntity();
		if (!$target instanceof Living) {
			return false;
		}

		if (!$this->mob->canAttack($target)) {
			return false;
		}

		return --$this->growTiredTimer > 0;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$this->attackTimer--;

		$target = $this->mob->getTargetEntity();
		if ($target !== null) {
			$this->mob->getLookControl()->setLookAt($target, 10, 10);

			if ($this->attackTimer <= 0 &&
				$this->mob->getAttackDamage() > 0 &&
				($bb = $this->mob->getBoundingBox())->intersectsWith($target->getBoundingBox())
			) {
				$attackValidator = $this->mob->getAttackableValidator();
				foreach ($this->mob->getWorld()->getCollidingEntities($bb, $this->mob) as $entity) {
					if ($entity instanceof Living && $attackValidator($entity)) {
						$this->mob->attackEntity($entity);
					}
				}

				$this->attackTimer = $this->attackCooldown;
			}
		}

		$this->mob->getMoveControl()->setDirection($this->mob->getLocation()->getYaw(), true);
	}
}
