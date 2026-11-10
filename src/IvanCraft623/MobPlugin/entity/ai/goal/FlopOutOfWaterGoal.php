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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\sound\EntityFlopSound;
use IvanCraft623\MobPlugin\utils\Utils;

/**
 * A water animal hops about when it lands out of water, and gets pulled down when no water is above its feet.
 */
class FlopOutOfWaterGoal extends Goal {

	private const FLOP_VERTICAL = 0.5;
	private const FLOP_HORIZONTAL = 0.2;
	private const DRY_PULL = 0.1;

	public function __construct(
		private Mob $mob
	) {
	}

	public function canUse() : bool{
		return ($this->mob->onGround && !$this->mob->isInWater()) || !$this->hasWaterAbove();
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		if ($this->mob->onGround && !$this->mob->isInWater()) {
			$this->flop();
		} elseif (!$this->hasWaterAbove()) {
			$this->mob->addMotion(0, -self::DRY_PULL, 0);
		}
	}

	private function flop() : void{
		$random = $this->mob->getRandom();
		$location = $this->mob->getLocation();

		$this->mob->addMotion(
			(2 * $random->nextFloat() - 1) * self::FLOP_HORIZONTAL,
			self::FLOP_VERTICAL,
			(2 * $random->nextFloat() - 1) * self::FLOP_HORIZONTAL
		);
		$this->mob->setRotation($random->nextFloat() * 360, $location->pitch);
		$this->mob->onGround = false;
		$this->mob->getWorld()->addSound($location, new EntityFlopSound($this->mob));
	}

	private function hasWaterAbove() : bool{
		$location = $this->mob->getLocation();

		return Utils::isWaterAt($this->mob->getWorld(), $location->getFloorX(), $location->getFloorY() + 1, $location->getFloorZ());
	}
}
