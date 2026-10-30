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

namespace IvanCraft623\MobPlugin\entity\ai\goal\enderman;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\TargetGoal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\monster\Enderman;

use pocketmine\player\Player;

class LookForStaringPlayerGoal extends TargetGoal {

	public const DEFAULT_RANDOM_INTERVAL = 10;

	protected int $aggroCooldownTicks = 0;

	protected int $randomInterval;

	protected TargetingConditions $startAggroTargetConditions;

	public function __construct(
		protected Enderman $enderman,
		?TargetingConditions $startAggroTargetConditions = null
	) {
		parent::__construct($enderman, false);

		$this->startAggroTargetConditions = $startAggroTargetConditions ?? (new TargetingConditions())
			->allowUnseeable()
			->testInvisible(false)
			->setRange($this->getFollowDistance());
		$this->randomInterval = $this->reducedTickDelay(self::DEFAULT_RANDOM_INTERVAL);

		$this->setFlags(Goal::FLAG_TARGET);
	}

	public function canUse() : bool{
		if ($this->aggroCooldownTicks > 0) {
			$this->aggroCooldownTicks--;
			return false;
		}

		if ($this->randomInterval > 0 && $this->enderman->getRandom()->nextBoundedInt($this->randomInterval) !== 0) {
			return false;
		}

		$this->findTarget();
		return $this->target !== null;
	}

	protected function findTarget() : void{
		$this->target = $this->enderman->getSensing()->findNearest(
			Player::class,
			$this->getFollowDistance(),
			filter: fn(Player $candidate) : bool => $this->canAttack($candidate, $this->startAggroTargetConditions) && $this->isAngerTriggering($candidate)
		);
	}

	public function canContinueToUse() : bool{
		if (!parent::canContinueToUse()) {
			return false;
		}

		if ($this->target instanceof Player && $this->isAngerTriggering($this->target)) {
			$this->enderman->startAngerTimer();
		}

		return true;
	}

	public function isAngerTriggering(Player $player) : bool{
		return $this->enderman->isLookingAtMe($player);
	}

	public function start() : void{
		$this->aggroCooldownTicks = $this->adjustedTickDelay(100);
		$this->enderman->setTargetEntity($this->target);
		$this->enderman->onBeingStaredAt();

		parent::start();
	}
}