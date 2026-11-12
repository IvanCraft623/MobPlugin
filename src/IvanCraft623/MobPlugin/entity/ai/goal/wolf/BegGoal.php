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

namespace IvanCraft623\MobPlugin\entity\ai\goal\wolf;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\animal\Wolf;
use pocketmine\entity\Living;
use pocketmine\player\Player;

/**
 * Looks at a nearby player holding something the wolf wants.
 */
class BegGoal extends Goal{

	private const MIN_LOOK_TIME = 20; //ticks
	private const MAX_LOOK_TIME = 40; //ticks
	private const CHECK_INTERVAL = 10; //ticks between scans for a player

	private TargetingConditions $conditions;

	private ?Player $begFrom = null;

	private int $lookTime = 0; //ticks

	private int $checkTimer = 0; //ticks until the next scan

	public function __construct(private Wolf $wolf, private float $lookDistance){
		$this->conditions = (new TargetingConditions($lookDistance))
			->allowInvulnerable()
			->allowNonAttackable()
			->setValidator(fn(Living $target) : bool => $target instanceof Player && $this->isHoldingBegItem($target));
		$this->setFlags(Goal::FLAG_LOOK);
	}

	private function isHoldingBegItem(Player $player) : bool{
		return $this->wolf->isBegItem($player->getInventory()->getItemInHand()) ||
			$this->wolf->isBegItem($player->getOffHandInventory()->getItem(0));
	}

	public function canUse() : bool{
		if (--$this->checkTimer > 0) {
			return false;
		}
		$this->checkTimer = $this->reducedTickDelay(self::CHECK_INTERVAL);

		$player = $this->wolf->getSensing()->findNearest(Player::class, $this->lookDistance, conditions: $this->conditions);
		if ($player === null) {
			return false;
		}

		$this->begFrom = $player;

		return true;
	}

	public function canContinueToUse() : bool{
		return $this->begFrom !== null &&
			$this->begFrom->isAlive() &&
			$this->lookTime > 0 &&
			$this->wolf->getPosition()->distanceSquared($this->begFrom->getPosition()) <= $this->lookDistance ** 2 &&
			$this->isHoldingBegItem($this->begFrom);
	}

	public function start() : void{
		$this->wolf->setBegging(true);
		$this->lookTime = $this->adjustedTickDelay(self::MIN_LOOK_TIME + $this->wolf->getRandom()->nextBoundedInt(self::MAX_LOOK_TIME - self::MIN_LOOK_TIME));
	}

	public function stop() : void{
		$this->wolf->setBegging(false);
		$this->begFrom = null;
		//The player is probably still holding the item: look again at once, not after the scan interval
		$this->checkTimer = 0;
	}

	public function tick() : void{
		if ($this->begFrom !== null) {
			$this->wolf->getLookControl()->setLookAt($this->begFrom);
		}
		--$this->lookTime;
	}
}
