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

namespace IvanCraft623\MobPlugin\entity\ai\goal\target;

use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\DamageTracker;
use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;

class TargetHighestDamagerGoal extends TargetGoal {

	protected TargetingConditions $targetingConditions;

	public function __construct(
		protected Mob&DamageTracker $mob,
		?TargetingConditions $conditions = null
	) {
		parent::__construct($mob, false, false);

		$this->targetingConditions = $conditions ?? (new TargetingConditions())
			->setRange($this->getFollowDistance());
	}

	public function canUse() : bool{
		$this->findTarget();
		return $this->target !== null;
	}

	public function getTargetSearchArea(float $range) : AxisAlignedBB{
		return $this->entity->getBoundingBox()->expandedCopy($range, $range, $range);
	}

	protected function findTarget() : void{
		$bestOption = null;
		$bestDamage = -1;
		foreach ($this->entity->getWorld()->getCollidingEntities(
			$this->getTargetSearchArea($this->getFollowDistance()), $this->entity
		) as $current) {
			if (!$current instanceof Living || !$this->targetingConditions->test($this->entity, $current)) {
				continue;
			}

			$currentDamage = $this->mob->getTotalDamageFrom($current);
			if ($currentDamage <= 0) {
				continue;
			}

			if ($currentDamage < $bestDamage) {
				continue;
			}

			$bestOption = $current;
			$bestDamage = $currentDamage;
		}

		$this->target = $bestOption;
	}

	public function start() : void{
		$this->entity->setTargetEntity($this->target);
		parent::start();
	}

	public function destroyCycles() : void{
		unset(
			$this->mob,
			$this->targetingConditions
		);
		parent::destroyCycles();
	}
}
