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
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\TargetGoal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\Pet;
use IvanCraft623\MobPlugin\memory\Memories;
use IvanCraft623\MobPlugin\memory\Memory;
use IvanCraft623\MobPlugin\memory\Occurrence;
use pocketmine\entity\Living;

/**
 * Targets what the owner remembers of its last fight, once per fight, while the pet stands.
 */
abstract class OwnerTargetGoal extends TargetGoal{

	private TargetingConditions $targetingConditions;

	private int $lastTick = -1; //server tick of the fight already answered

	private ?Memory $pending = null; //the fight that is about to start

	protected function __construct(
		private Mob&Pet $pet,
		private Occurrence $occurrence
	){
		parent::__construct($pet, false);

		$this->targetingConditions = HurtByTargetGoal::HURT_BY_TARGETING();
		$this->setFlags(Goal::FLAG_TARGET);
	}

	public function canUse() : bool{
		if ($this->pet->isSitting()) {
			return false;
		}

		$owner = $this->pet->getOwningEntity();
		if ($owner === null) {
			return false;
		}

		$memory = Memories::getInstance()->latest($owner, $this->occurrence, Mob::HURT_BY_MEMORY_TICKS);
		if ($memory === null || $memory->getTick() === $this->lastTick) {
			return false;
		}

		$target = $memory->getEntity();
		if (!$target instanceof Living || !$this->canAttack($target, $this->targetingConditions)) {
			return false;
		}

		$this->target = $target;
		$this->pending = $memory;

		return true;
	}

	public function start() : void{
		/** @var Memory $fight */
		$fight = $this->pending;

		$this->lastTick = $fight->getTick();
		$this->entity->setTargetEntity($this->target);

		parent::start();
	}
}
