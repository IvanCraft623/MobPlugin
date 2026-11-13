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

use IvanCraft623\MobPlugin\bed\BedListener;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\entity\Pet;
use IvanCraft623\MobPlugin\entity\SleepsWithOwner;
use pocketmine\block\Bed;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function abs;

/**
 * Walks a tamed cat to its sleeping owner's bed and lies on it until they wake up. One cat per bed.
 */
class SleepWithOwnerGoal extends Goal{

	private const SETTLE_TICKS = 20;
	private const TURN_TICKS = 15; //ticks the cat turns toward the bed, out of the settle ticks
	private const MAX_TURN_DEGREES = 30; //per tick
	private const REPATH_INTERVAL = 40; //ticks

	private ?Player $owner = null;

	private Position $bed;

	private Vector3 $spot;

	private int $walkTicks = 0;

	/** Ticks left until the cat lies down, -1 while it is walking. */
	private int $settleTicks = -1;

	/**
	 * @param int   $searchHeight blocks the cat may be above or below its owner
	 * @param float $goalRadius   blocks from the bed's center that count as arrived
	 */
	public function __construct(
		private PathfinderMob&Pet&SleepsWithOwner $mob,
		private float $speedModifier,
		private int $searchHeight,
		private float $goalRadius
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		if (!$this->mob->isTamed() || $this->mob->isSitting()) {
			return false;
		}

		$owner = $this->mob->getOwningEntity();
		if (!$owner instanceof Player || !$owner->isSleeping()) {
			return false;
		}

		// Sleeping sets the spawn point to the bed
		$bed = $owner->getSpawn();
		if ($bed->getWorld() !== $this->mob->getWorld() || !$bed->getWorld()->getBlock($bed) instanceof Bed ||
			abs($owner->getPosition()->y - $this->mob->getPosition()->y) > $this->searchHeight ||
			!BedListener::getInstance()->claim($bed, $this->mob)
		) {
			return false;
		}

		$this->owner = $owner;
		$this->bed = $bed;
		$this->spot = $bed->add(0.5, 0.5, 0.5);

		return true;
	}

	public function canContinueToUse() : bool{
		return $this->owner !== null && !$this->owner->isClosed() && $this->owner->isSleeping() && !$this->mob->isSitting();
	}

	public function start() : void{
		$this->walkTicks = 0;
		$this->settleTicks = -1;
		$this->walkToBed();
	}

	public function tick() : void{
		if ($this->settleTicks === 0) {
			return;
		}

		if ($this->settleTicks < 0) {
			$this->tickWalking();

			return;
		}

		$this->tickSettling();
	}

	public function stop() : void{
		$this->owner = null;
		$this->mob->setResting(false);
		$this->mob->getNavigation()->stop();
		BedListener::getInstance()->release($this->bed, $this->mob);
	}

	private function tickWalking() : void{
		$position = $this->mob->getPosition();
		$distanceSquared = $this->spot->distanceSquared($position->add(0, $this->mob->getSize()->getHeight() / 2, 0));
		if ($distanceSquared < $this->goalRadius) {
			$this->settleTicks = self::SETTLE_TICKS;

			return;
		}

		if (++$this->walkTicks % $this->reducedTickDelay(self::REPATH_INTERVAL) === 0) {
			$this->walkToBed();
		}
	}

	private function tickSettling() : void{
		--$this->settleTicks;
		if ($this->settleTicks <= self::SETTLE_TICKS - self::TURN_TICKS) {
			if ($this->settleTicks === 0) {
				$this->mob->getNavigation()->stop();
				$this->mob->setResting(true);
			}

			return;
		}

		$this->mob->setMotion($this->mob->getMotion()->multiply(0.5));
		$this->mob->getLookControl()->setLookAt($this->spot, self::MAX_TURN_DEGREES, self::MAX_TURN_DEGREES);
	}

	private function walkToBed() : void{
		$this->mob->getNavigation()->moveToXYZ($this->spot->x, $this->bed->y, $this->spot->z, $this->speedModifier);
	}
}
