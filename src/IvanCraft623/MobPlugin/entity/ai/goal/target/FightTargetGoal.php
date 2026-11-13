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
use IvanCraft623\MobPlugin\memory\Memory;
use pocketmine\entity\Living;

/**
 * Targets the other side of a fight someone else remembers, once per fight.
 */
abstract class FightTargetGoal extends TargetGoal{

	private TargetingConditions $targetingConditions;

	private int $lastTick = -1; //server tick of the fight already answered

	private ?Memory $pending = null; //the fight that is about to start

	protected function __construct(Mob $mob){
		parent::__construct($mob, false);

		$this->targetingConditions = HurtByTargetGoal::HURT_BY_TARGETING();
		$this->setFlags(Goal::FLAG_TARGET);
	}

	/**
	 * Takes the fight as the one to answer, unless it is over, already answered, or against something that can't be attacked.
	 */
	protected function answer(?Memory $fight) : bool{
		if ($fight === null || $fight->getTick() === $this->lastTick) {
			return false;
		}

		$target = $fight->getEntity();
		if (!$target instanceof Living || $target === $this->entity || !$this->canAttack($target, $this->targetingConditions)) {
			return false;
		}

		$this->target = $target;
		$this->pending = $fight;

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
