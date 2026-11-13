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
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\entity\Tameable;
use IvanCraft623\MobPlugin\memory\Memories;
use IvanCraft623\MobPlugin\memory\Occurrence;
use pocketmine\math\Vector3;

/**
 * Brings a tamed mob to its owner when it is panicking or fighting too far from them. It needs no movement, so it
 * works where {@see FollowOwnerGoal} can't.
 */
class TeleportToOwnerGoal extends Goal{

	private ?Vector3 $destination = null;

	/**
	 * @param float      $panicDistance  blocks from the owner past which a panicking mob teleports
	 * @param float|null $combatDistance blocks from the owner past which a mob with a target teleports, null for never
	 */
	public function __construct(
		private PathfinderMob&Tameable $mob,
		private float $panicDistance,
		private ?float $combatDistance = null
	){
	}

	public function canUse() : bool{
		$owner = OwnerTeleport::getOwner($this->mob);
		if ($owner === null) {
			return false;
		}

		$distanceSquared = $this->mob->getPosition()->distanceSquared($owner->getPosition());
		$panicking = Memories::getInstance()->latest($this->mob, Occurrence::HURT_BY, Mob::HURT_BY_MEMORY_TICKS) !== null ||
			$this->mob->isOnFire();
		$fighting = $this->combatDistance !== null && $this->mob->getTargetEntity() !== null &&
			$distanceSquared > $this->combatDistance ** 2;
		if (!($panicking && $distanceSquared > $this->panicDistance ** 2) && !$fighting) {
			return false;
		}

		$destination = OwnerTeleport::findDestination($this->mob, $owner);
		if ($destination === null) {
			return false;
		}

		$this->destination = $destination;

		return true;
	}

	public function canContinueToUse() : bool{
		return false;
	}

	public function start() : void{
		if ($this->destination !== null) {
			OwnerTeleport::teleport($this->mob, $this->destination);
		}
	}

	public function stop() : void{
		$this->destination = null;
	}
}