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
use IvanCraft623\MobPlugin\entity\Trusting;
use pocketmine\entity\Living;
use pocketmine\player\Player;

/**
 * Runs away from the players the mob doesn't trust, and from other kinds of entity.
 */
class AvoidUntrustedEntitiesGoal extends AvoidEntityGoal{

	private Trusting $trusting;

	/**
	 * @param float                  $maxDistance    blocks within which it avoids
	 * @param class-string<Living>[] $otherTypes     avoided whoever they are
	 * @param bool                   $sneakingIsSafe whether it ignores sneaking players
	 */
	public function __construct(
		PathfinderMob&Trusting $mob,
		float $maxDistance,
		float $walkSpeedModifier,
		float $sprintSpeedModifier,
		array $otherTypes = [],
		private bool $sneakingIsSafe = false
	){
		$this->trusting = $mob;
		parent::__construct($mob, [Player::class, ...$otherTypes], $maxDistance, $walkSpeedModifier, $sprintSpeedModifier);
	}

	protected function isAvoided(Living $candidate) : bool{
		if ($candidate instanceof Player) {
			return !$this->trusting->isTrusting($candidate) && !($this->sneakingIsSafe && $candidate->isSneaking());
		}

		return parent::isAvoided($candidate);
	}
}