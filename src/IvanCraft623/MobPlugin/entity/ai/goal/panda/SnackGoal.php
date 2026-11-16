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
use pocketmine\entity\animation\ConsumingItemAnimation;
use pocketmine\item\VanillaItems;
use function max;

/**
 * Sits down with a snack in its mouth and eats it now and then, until it has eaten it all or got bored.
 */
final class SnackGoal extends Goal{

	private const START_EATING_INTERVAL = 80; //1 in this many ticks that it starts eating, once sat with a snack
	private const ANIMATION_INTERVAL = 5; //ticks between bites
	private const MIN_EATING_TICKS = 80; //ticks of eating before it may stop
	private const STOP_EATING_INTERVAL = 20; //1 in this many ticks that it stops, once it may
	private const FINISH_TICKS = 100; //ticks of eating that finish the snack

	private int $eatingTicks = 0; //ticks it has been eating, 0 if it isn't

	/**
	 * @param int   $cooldown    most ticks between snacks
	 * @param int   $minCooldown least ticks between snacks
	 * @param float $stopChance  chance, out of 1 per check, of leaving the snack
	 */
	public function __construct(
		private Panda $panda,
		private int $cooldown,
		private int $minCooldown,
		private float $stopChance
	){
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		return $this->panda->canSnack() && $this->panda->isSnack($this->panda->getInventory()->getItemInHand());
	}

	public function canContinueToUse() : bool{
		return !$this->panda->isInWater() &&
			$this->panda->isSnack($this->panda->getInventory()->getItemInHand()) &&
			$this->panda->getRandom()->nextFloat() >= $this->stopChance * 2; //checked every second tick
	}

	public function start() : void{
		$this->panda->getNavigation()->stop();
		$this->panda->setSitting(true);
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$panda = $this->panda;
		if (!$panda->isSitting()) {
			$panda->setSitting(true);
		}

		$random = $panda->getRandom();
		if (!$panda->isEating()) {
			if ($random->nextBoundedInt(self::START_EATING_INTERVAL) !== 1) {
				return;
			}
			$panda->setEating(true);
		}

		$held = $panda->getInventory()->getItemInHand();
		if ($this->eatingTicks % self::ANIMATION_INTERVAL === 0) {
			$panda->broadcastAnimation(new ConsumingItemAnimation($panda, $held));
		}
		if ($this->eatingTicks > self::MIN_EATING_TICKS && $random->nextBoundedInt(self::STOP_EATING_INTERVAL) === 1) {
			if ($this->eatingTicks > self::FINISH_TICKS) {
				$panda->getInventory()->setItemInHand(VanillaItems::AIR());
			}
			$this->eatingTicks = 0;
			$panda->setEating(false);

			return;
		}

		++$this->eatingTicks;
	}

	public function stop() : void{
		$this->eatingTicks = 0;
		$this->panda->setEating(false);

		//What it didn't eat is left on the ground
		$held = $this->panda->getInventory()->getItemInHand();
		if (!$held->isNull()) {
			$this->panda->getWorld()->dropItem($this->panda->getPosition(), $held);
			$this->panda->getInventory()->setItemInHand(VanillaItems::AIR());
		}
		$this->panda->setSitting(false);

		$this->panda->startSnackCooldown(
			$this->minCooldown + $this->panda->getRandom()->nextBoundedInt(max(1, $this->cooldown - $this->minCooldown))
		);
	}
}