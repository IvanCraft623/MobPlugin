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

namespace IvanCraft623\MobPlugin\entity\ai\goal\wither;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\monster\skeleton\WitherSkeleton;

use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class WitherExplodeOnHalfLifeGoal extends Goal {

	public const DEFAULT_TIME_OUT_TICKS = 40;

	protected bool $hasExploded = false;

	public function __construct(
		protected Wither $mob,
		protected float $speedModifier,
		protected int $timeoutTicks = self::DEFAULT_TIME_OUT_TICKS
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		return !$this->mob->isPowered() && $this->mob->getHealth() <= ($this->mob->getMaxHealth() / 2);
	}

	public function canContinueToUse() : bool{
		return $this->mob->isPowered() && !$this->mob->isOnGround() && $this->timeoutTicks > 0;
	}

	public function start() : void{
		$this->mob->setPowered(true);

		$this->mob->setMotion(Vector3::zero());
		$this->mob->getNavigation()->stop();

		if (($target = $this->mob->getTargetEntity()) !== null &&
			$target->getPosition()->distanceSquared($this->mob->getPosition()) < $this->mob->getExplosionRadius() ** 2
		) {
			$this->explode();
		}
	}

	public function stop() : void {
		if (!$this->hasExploded) {
			$this->explode();
		}
	}

	public function requiresUpdateEveryTick() : bool {
		return true;
	}

	public function tick() : void {
		$position = $this->mob->getPosition();
		$this->mob->addMotion(
			0,
			-$this->speedModifier * $this->mob->getFlyingSpeed(),
			0
		);

		$this->timeoutTicks--;
	}

	protected function explode() : void {
		$this->mob->explode();

		$world = $this->mob->getWorld();
		$witherLoc = $this->mob->getLocation();
		$spawnLoc = Location::fromObject(
			$world->getSafeSpawn($witherLoc->floor()->add(0.5, 0, 0.5)),
			$world,
			$witherLoc->yaw
		);

		if ($world->getDifficulty() >= World::DIFFICULTY_NORMAL) {
			for ($i = 0; $i < 3; $i++) {
				$witherSkeleton = new WitherSkeleton($spawnLoc);
				$witherSkeleton->setOwningEntity($this->mob);
				$witherSkeleton->spawnToAll();
			}
		}

		$this->hasExploded = true;
	}
}
