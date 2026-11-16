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
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\sound\PandaPreSneezeSound;
use IvanCraft623\MobPlugin\sound\PandaSneezeSound;

/**
 * Sneezes after a moment to get ready, startling the adult pandas close by, and sometimes blowing out a slime ball.
 */
final class SneezeGoal extends Goal{

	private const PREPARE_TICKS = 20; //ticks
	private const DROP_CHANCE = 0.001; //out of 1 per sneeze, of dropping what its loot table gives

	private int $prepareTicks = 0;

	/**
	 * @param float $probability chance, out of 1 per tick, of starting to sneeze
	 */
	public function __construct(
		private Panda $panda,
		private float $probability
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		return $this->panda->isOnGround() &&
			$this->panda->isIdle() &&
			$this->panda->getRandom()->nextFloat() < $this->probability * 2; //checked every second tick
	}

	public function canContinueToUse() : bool{
		return $this->prepareTicks < self::PREPARE_TICKS;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->prepareTicks = 0;
		$this->panda->getNavigation()->stop();
		$this->panda->setSneezing(true);
		$this->panda->getWorld()->addSound($this->panda->getPosition(), new PandaPreSneezeSound());
	}

	public function tick() : void{
		if (++$this->prepareTicks >= self::PREPARE_TICKS) {
			$this->sneeze();
		}
	}

	public function stop() : void{
		$this->panda->setSneezing(false);
	}

	private function sneeze() : void{
		$this->panda->getWorld()->addSound($this->panda->getPosition(), new PandaSneezeSound());

		foreach ($this->panda->getSensing()->getNearbyLiving(10) as $other) { //blocks
			if ($other instanceof Panda && !$other->isBaby() && $other->isOnGround() && !$other->isInWater() && $other->isIdle()) {
				$other->jump();
			}
		}

		if ($this->panda->getRandom()->nextFloat() < self::DROP_CHANCE) {
			$this->panda->dropLoot(VanillaLootTables::PANDA_SNEEZE(), null, null);
		}
	}
}