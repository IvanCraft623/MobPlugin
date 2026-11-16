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

use IvanCraft623\MobPlugin\entity\ai\goal\target\FightTargetGoal;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\Pet;
use IvanCraft623\MobPlugin\memory\Memories;
use IvanCraft623\MobPlugin\memory\Occurrence;

/**
 * Targets what the owner remembers of its last fight, once per fight, while the pet stands.
 */
abstract class OwnerTargetGoal extends FightTargetGoal{

	protected function __construct(
		private Mob&Pet $pet,
		private Occurrence $occurrence
	){
		parent::__construct($pet);
	}

	public function canUse() : bool{
		if ($this->pet->isSitting()) {
			return false;
		}

		$owner = $this->pet->getOwningEntity();

		return $owner !== null && $this->answer(Memories::getInstance()->latest($owner, $this->occurrence, Mob::HURT_BY_MEMORY_TICKS));
	}
}