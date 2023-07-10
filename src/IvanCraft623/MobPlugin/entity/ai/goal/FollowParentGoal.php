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

use IvanCraft623\MobPlugin\entity\animal\Animal;

use pocketmine\math\AxisAlignedBB;
use const INF;

class FollowParentGoal extends Goal {

	public const HORIZONTAL_SEARCH_RANGE = 8;
	public const VERTICAL_SEARCH_RANGE = 4;

	public const DONT_FOLLOW_IF_CLOSER_THAN = 3;

	private Animal $parent;

	private int $ticksToRecalculatePath = 0;

	public function __construct(
		protected Animal $entity,
		protected float $speedModifier
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function getParentSearchArea() : AxisAlignedBB{
		return $this->entity->getBoundingBox()->expandedCopy(self::HORIZONTAL_SEARCH_RANGE, self::VERTICAL_SEARCH_RANGE, self::HORIZONTAL_SEARCH_RANGE);
	}

	public function canUse() : bool {
		if (!$this->entity->isBaby()) {
			return false;
		}

		$parent = null;
		$bestDistanceSquared = INF;

		foreach ($this->entity->getWorld()->getCollidingEntities($this->getParentSearchArea(), $this->entity) as $entity) {
			if ($entity::class !== $this->entity::class) {
				continue;
			}

			/** @var Animal $entity */
			if ($entity->isBaby()) {
				continue;
			}

			$distanceSquared = $entity->getPosition()->distanceSquared($this->entity->getPosition());
			if ($distanceSquared < (self::DONT_FOLLOW_IF_CLOSER_THAN ** 2)) {
				continue;
			}

			if ($distanceSquared < $bestDistanceSquared) {
				$parent = $entity;
				$bestDistanceSquared = $distanceSquared;
			}
		}

		if ($parent !== null) {
			$this->parent = $parent;

			return true;
		}

		return false;
	}

	public function canContinueToUse() : bool {
		if (!$this->entity->isBaby()) {
			return false;
		}
		if (!$this->parent->isAlive() || $this->parent->isClosed()) {
			return false;
		}

		$distanceSquared = $this->entity->getPosition()->distanceSquared($this->parent->getPosition());
		if ($distanceSquared < (self::DONT_FOLLOW_IF_CLOSER_THAN ** 2)) {
			return false;
		}
		if ($distanceSquared > ($this->entity->getFollowRange() ** 2)) {
			return false;
		}

		return true;
	}

	public function start() : void {
		$this->ticksToRecalculatePath = 0;
	}

	public function stop() : void {
		unset($this->parent);
	}

	public function tick() : void {
		if (--$this->ticksToRecalculatePath) {
			$this->ticksToRecalculatePath = $this->adjustedTickDelay(10);
			$this->entity->getNavigation()->moveToEntity($this->parent, $this->speedModifier);
		}
	}
}
