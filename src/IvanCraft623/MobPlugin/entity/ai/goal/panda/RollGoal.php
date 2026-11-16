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

namespace IvanCraft623\MobPlugin\entity\ai\goal\panda;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\animal\Panda;
use pocketmine\math\Vector3;
use function cos;
use function deg2rad;
use function sin;

/**
 * Hops forward and rolls over a few blocks.
 */
final class RollGoal extends Goal{

	private const ADULT_PUSH = 0.2; //blocks per tick forward, at the start
	private const BABY_PUSH = 0.1; //blocks per tick forward, at the start
	private const HOP = 0.27; //blocks per tick up, at the start and then every HOP_INTERVAL ticks
	private const HOP_INTERVAL = 8; //ticks between hops, counted from the roll's first, which is one itself
	private const ROLL_TICKS = 32; //ticks the roll lasts

	private Vector3 $push;

	private int $rollTicks = 0;

	/**
	 * @param float $probability chance, out of 1 per tick, of starting to roll
	 */
	public function __construct(
		private Panda $panda,
		private float $probability
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return $this->panda->isOnGround() &&
			!$this->panda->isSitting() &&
			!$this->panda->isInWater() &&
			$this->panda->getRandom()->nextFloat() < $this->probability * 2; //checked every second tick
	}

	public function canContinueToUse() : bool{
		return $this->rollTicks < self::ROLL_TICKS;
	}

	public function isInterruptable() : bool{
		return false;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->rollTicks = 0;
		$this->panda->getNavigation()->stop();
		$this->panda->setRolling(true);
	}

	public function stop() : void{
		$this->panda->setRolling(false);
	}

	/**
	 * Hops forward, then rolls along with a hop every 8 ticks.
	 */
	public function tick() : void{
		$motion = $this->panda->getMotion();
		$tick = $this->rollTicks++;
		if ($tick === 0) {
			$yaw = deg2rad($this->panda->getLocation()->yaw);
			$push = $this->panda->isBaby() ? self::BABY_PUSH : self::ADULT_PUSH;
			$this->push = new Vector3($motion->x - sin($yaw) * $push, 0, $motion->z + cos($yaw) * $push);
			$this->panda->setMotion($this->push->add(0, self::HOP, 0));
		} elseif ($tick % self::HOP_INTERVAL === 0) {
			$this->panda->setMotion(new Vector3(0, $this->panda->isOnGround() ? self::HOP : $motion->y, 0));
		} else {
			$this->panda->setMotion(new Vector3($this->push->x, $motion->y, $this->push->z));
		}
	}
}