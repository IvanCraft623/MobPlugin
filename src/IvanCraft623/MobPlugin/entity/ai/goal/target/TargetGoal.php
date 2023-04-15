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

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\entity\Living;

abstract class TargetGoal extends Goal {

	protected ?Living $target = null;

	protected bool $canReachCache;
	protected int $reachCheckTime = 0;

	private int $unseenTicks = 0;

	protected int $unseenMemoryTicks = 60;

	public function __construct(
		protected Mob $entity,
		protected bool $mustSee,
		protected bool $mustReach = false
	) {
	}

	public function canContinueToUse() : bool{
		$target = $this->entity->getTargetEntity() ?? $this->target;
		if ($target === null || !$this->entity->canAttack($target)) {
			return false;
		}

		$entityPos = $this->entity->getPosition();
		$targetPos = $target->getPosition();
		if ($entityPos->world !== $targetPos->world ||
			$entityPos->distanceSquared($targetPos) > $this->getFollowDistance() ** 2
		) {
			return false;
		}

		if ($this->mustSee) {
			if ($this->entity->getSensing()->canSee($target)) {
				$this->unseenTicks = 0;
			} elseif (++$this->unseenTicks > $this->reducedTickDelay($this->unseenMemoryTicks)) {
				return false;
			}
		}

		$this->entity->setTargetEntity($target);

		return true;
	}

	public function getFollowDistance() : float{
		return $this->entity->getFollowRange();
	}

	public function start() : void{
		unset($this->canReachCache);
		$this->reachCheckTime = 0;
		$this->unseenTicks = 0;
	}

	public function stop() : void{
		$this->target = null;
		$this->entity->setTargetEntity(null);
	}

	public function canAttack(?Living $victim, TargetingConditions $conditions) : bool{
		if ($victim === null ||
			!$conditions->test($this->entity, $victim) ||
			!$this->entity->isWithinRestriction($victim->getPosition())
		) {
			return false;
		}

		if ($this->mustReach) {
			if (--$this->reachCheckTime <= 0) {
				unset($this->canReachCache);
			}

			if (!isset($this->canReachCache)) {
				$this->canReachCache = $this->canReach($victim);
			}

			if (!$this->canReachCache) {
				return false;
			}
		}

		return true;
	}

	private function canReach(Living $victim) : bool{
		$this->reachCheckTime = $this->reducedTickDelay(10 + $this->entity->getRandom()->nextBoundedInt(5));

		$path = $this->mob->getNavigation()->createPathToEntity($victom, 0);
		if ($path === null) {
			return false;
		}

		$endNode = $path->getEndNode();
		if ($endNode === null) {
			return false;
		}

		$diff = $endNode->subtractVector($victim->getPosition()->floor());
		return ($diff->x ** 2) + ($diff->z ** 2) <= 2.25;
	}

	public function setUnseenMemoryTicks(int $ticks) : self{
		$this->unseenMemoryTicks = $ticks;
	}
}
