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

namespace IvanCraft623\MobPlugin\entity\ai\goal\feline;

use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\PathfinderMob;

/**
 * Melee attack that creeps up on the target and sprints the last blocks.
 */
class StalkAttackGoal extends MeleeAttackGoal{

	/**
	 * @param float $sneakSpeedModifier  speed while creeping up
	 * @param float $sprintSpeedModifier speed while close
	 * @param float $sprintRange         blocks from the target at which it sprints
	 * @param float $sneakRange          blocks from the target at which it creeps up
	 */
	public function __construct(
		PathfinderMob $mob,
		private float $walkSpeedModifier,
		private float $sneakSpeedModifier,
		private float $sprintSpeedModifier,
		private float $sprintRange,
		private float $sneakRange
	){
		parent::__construct($mob, $walkSpeedModifier);
	}

	public function tick() : void{
		$target = $this->mob->getTargetEntity();
		if ($target === null) {
			return;
		}

		$distanceSquared = $this->mob->getPosition()->distanceSquared($target->getPosition());
		$sneaking = $distanceSquared > $this->sprintRange ** 2 && $distanceSquared <= $this->sneakRange ** 2;
		$this->speedModifier = match(true){
			$distanceSquared <= $this->sprintRange ** 2 => $this->sprintSpeedModifier,
			$sneaking => $this->sneakSpeedModifier,
			default => $this->walkSpeedModifier
		};
		$this->mob->setSneaking($sneaking);

		parent::tick();
	}

	public function stop() : void{
		$this->mob->setSneaking(false);

		parent::stop();
	}
}