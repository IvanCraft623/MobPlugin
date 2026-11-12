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

namespace IvanCraft623\MobPlugin\entity\ai\goal\tame;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\entity\Pet;
use pocketmine\entity\Entity;

/**
 * Walks a tamed mob to its owner once they are far enough, until it is close again. Past the teleport distance it
 * teleports instead. It can't run while the mob panics or fights, as their goals hold the movement: see
 * {@see TeleportToOwnerGoal}.
 */
class FollowOwnerGoal extends Goal{

	private ?Entity $owner = null;

	/**
	 * @param float      $startDistance    blocks from the owner at which the mob starts following
	 * @param float      $stopDistance     blocks from the owner at which the mob stops
	 * @param float|null $teleportDistance blocks from the owner at which the mob teleports to them instead, null for never
	 */
	public function __construct(
		private PathfinderMob&Pet $mob,
		private float $speedModifier,
		private float $startDistance,
		private float $stopDistance,
		private ?float $teleportDistance = null
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		if ($this->mob->isSitting()) {
			return false;
		}

		$owner = OwnerTeleport::getOwner($this->mob);
		if ($owner === null || $this->mob->getPosition()->distanceSquared($owner->getPosition()) < $this->startDistance ** 2) {
			return false;
		}

		$this->owner = $owner;

		return true;
	}

	public function canContinueToUse() : bool{
		return $this->owner !== null &&
			!$this->owner->isClosed() &&
			!$this->mob->isSitting() &&
			!$this->mob->getNavigation()->isDone() &&
			$this->mob->getPosition()->distanceSquared($this->owner->getPosition()) > $this->stopDistance ** 2;
	}

	public function start() : void{
		if ($this->owner !== null) {
			$this->mob->getNavigation()->moveToEntity($this->owner, $this->speedModifier);
		}
	}

	public function tick() : void{
		if ($this->owner === null) {
			return;
		}

		$this->mob->getLookControl()->setLookAt($this->owner);
		if ($this->tryToTeleport()) {
			return;
		}
		$this->mob->getNavigation()->moveToEntity($this->owner, $this->speedModifier);
	}

	private function tryToTeleport() : bool{
		if ($this->owner === null || $this->teleportDistance === null ||
			$this->mob->getPosition()->distanceSquared($this->owner->getPosition()) < $this->teleportDistance ** 2
		) {
			return false;
		}

		$destination = OwnerTeleport::findDestination($this->mob, $this->owner);
		if ($destination === null) {
			return false;
		}

		OwnerTeleport::teleport($this->mob, $destination);

		return true;
	}

	public function stop() : void{
		$this->owner = null;
		$this->mob->getNavigation()->stop();
	}
}
