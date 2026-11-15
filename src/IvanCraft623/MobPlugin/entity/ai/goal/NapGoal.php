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

use Closure;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\entity\Sleepable;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * Falls asleep, and keeps sleeping until the nap is no longer allowed or a mob comes near.
 */
class NapGoal extends Goal {

	private const WAKE_CHECK_INTERVAL = 20; //ticks between looks for mobs nearby

	private Sleepable $sleeper;

	private int $nextNapTick; //server tick before which it won't nap

	private int $nextWakeCheckTick = 0; //server tick of the next look for mobs nearby

	/**
	 * @param int                    $minCooldown     ticks between waking and napping again, at least
	 * @param int                    $maxCooldown     see $minCooldown, one less is the most
	 * @param float                  $detectDistance  blocks around the mob where a mob wakes it
	 * @param float                  $detectHeight    blocks above and below the mob where a mob wakes it
	 * @param Closure() : bool       $canNap          whether it may nap now
	 * @param Closure(Living) : bool $isWakeException whether a mob nearby leaves it asleep
	 */
	public function __construct(
		protected PathfinderMob&Sleepable $entity,
		private int $minCooldown,
		private int $maxCooldown,
		private float $detectDistance,
		private float $detectHeight,
		private Closure $canNap,
		private Closure $isWakeException
	) {
		$this->sleeper = $entity;
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK, Goal::FLAG_JUMP);
		$this->nextNapTick = $entity->getServer()->getTick() + $this->rollCooldown();
	}

	public function canUse() : bool{
		return $this->entity->getServer()->getTick() >= $this->nextNapTick &&
			($this->canNap)() &&
			!$this->isDisturbed();
	}

	public function canContinueToUse() : bool{
		$tick = $this->entity->getServer()->getTick();
		if ($tick > $this->nextWakeCheckTick) {
			$this->nextWakeCheckTick = $tick + self::WAKE_CHECK_INTERVAL;
			if ($this->isDisturbed()) {
				return false;
			}
		}

		return ($this->canNap)();
	}

	public function start() : void{
		$this->entity->getNavigation()->stop();
		$this->entity->setMotion(Vector3::zero());
		$this->sleeper->setSleeping(true);
		$this->nextWakeCheckTick = $this->entity->getServer()->getTick() + self::WAKE_CHECK_INTERVAL;
	}

	public function stop() : void{
		parent::stop();

		$this->sleeper->setSleeping(false);
		$this->entity->setMotion(Vector3::zero());
		$this->nextNapTick = $this->entity->getServer()->getTick() + $this->rollCooldown();
	}

	private function rollCooldown() : int{
		return $this->minCooldown + $this->entity->getRandom()->nextBoundedInt($this->maxCooldown - $this->minCooldown);
	}

	/**
	 * Whether a mob that wouldn't leave it asleep is near.
	 */
	private function isDisturbed() : bool{
		$box = $this->entity->getBoundingBox()->expandedCopy($this->detectDistance, $this->detectHeight, $this->detectDistance);
		foreach ($this->entity->getWorld()->getNearbyEntities($box, $this->entity) as $other) {
			if (!$other instanceof Living || ($other instanceof Player && $other->isCreative(true))) {
				continue;
			}
			if (!($this->isWakeException)($other)) {
				return true;
			}
		}

		return false;
	}
}
