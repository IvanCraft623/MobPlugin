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

namespace IvanCraft623\MobPlugin\entity\ai\goal\cat;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\animal\Cat;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use pocketmine\player\Player;
use pocketmine\world\World;

/**
 * Gives a nearby player a gift once, in the morning after the cat slept with its owner.
 */
class GiftToOwnerGoal extends Goal{

	private const COOLDOWN = 5; //ticks
	private const MAX_HEAD_HEIGHT = 10; //blocks the cat looks up

	private TargetingConditions $conditions;

	private int $cooldown = 0; //ticks left

	private ?Player $target = null;

	private bool $tried = false;

	/**
	 * @param float $dropChance     probability, from 0 to 1
	 * @param float $startTimeOfDay earliest time of day (0 is noon, 0.75 sunrise)
	 * @param float $endTimeOfDay   latest time of day
	 * @param float $targetDistance blocks from the cat to the player
	 */
	public function __construct(
		private Cat $mob,
		private float $dropChance,
		private float $startTimeOfDay,
		private float $endTimeOfDay,
		private float $targetDistance
	){
		$this->conditions = (new TargetingConditions($targetDistance))
			->allowInvulnerable()
			->allowNonAttackable()
			->allowUnseeable();
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		if (!$this->mob->hasGiftPending()) {
			return false;
		}

		if ($this->cooldown > 0) {
			--$this->cooldown;

			return false;
		}

		return true;
	}

	public function canContinueToUse() : bool{
		return $this->target !== null && !$this->target->isClosed() && !$this->tried;
	}

	public function start() : void{
		$this->tried = false;
		$this->target = null;
		if (!$this->isTimeOfDayValid()) {
			return;
		}

		$target = $this->mob->getSensing()->findNearest(Player::class, $this->targetDistance, conditions: $this->conditions);
		if ($target instanceof Player) {
			$this->target = $target;
		}
	}

	public function tick() : void{
		if ($this->target === null) {
			return;
		}

		$this->mob->getLookControl()->setLookAt($this->target->getEyePos(), self::MAX_HEAD_HEIGHT * 3, self::MAX_HEAD_HEIGHT * 3);
		if ($this->tried) {
			return;
		}

		$this->tried = true;
		if ($this->mob->getRandom()->nextFloat() <= $this->dropChance) {
			$this->mob->dropLoot(VanillaLootTables::CAT_GIFT(), $this->target, null, 0);
		}
	}

	public function stop() : void{
		$this->mob->setGiftPending(false);
		$this->target = null;
		$this->tried = false;
		$this->cooldown = $this->reducedTickDelay(self::COOLDOWN);
	}

	private function isTimeOfDayValid() : bool{
		$ticks = ($this->mob->getWorld()->getTimeOfDay() - World::TIME_NOON + World::TIME_FULL) % World::TIME_FULL;
		$timeOfDay = $ticks / World::TIME_FULL;

		return $timeOfDay >= $this->startTimeOfDay && $timeOfDay <= $this->endTimeOfDay;
	}
}