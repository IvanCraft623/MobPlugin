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

namespace IvanCraft623\MobPlugin\entity\ai\goal\rabbit;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\utils\NearestBlockFinder;
use IvanCraft623\MobPlugin\entity\PathfinderMob;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Carrot;
use pocketmine\math\Vector3;

/**
 * Walks to a grown carrot and eats it, leaving the plant to grow again.
 */
class RaidGardenGoal extends Goal {

	private const SEARCH_INTERVAL = 40; //average ticks between searches
	private const SEARCH_HEIGHT = 2; //blocks
	private const EAT_DISTANCE_SQUARED = 1.5; //blocks squared

	private Vector3 $crop;

	private bool $ate = false;

	public function __construct(
		protected PathfinderMob $mob,
		protected float $speedModifier,
		protected int $searchRange = 16
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		if ($this->mob->getRandom()->nextBoundedInt($this->reducedTickDelay(self::SEARCH_INTERVAL)) !== 0) {
			return false;
		}

		$crop = $this->findCrop();
		if ($crop === null) {
			return false;
		}

		$this->crop = $crop;

		return true;
	}

	public function canContinueToUse() : bool{
		return !$this->ate && $this->isGrown($this->crop) && !$this->mob->getNavigation()->isDone();
	}

	public function start() : void{
		$this->mob->getNavigation()->moveToXYZ($this->crop->x + 0.5, $this->crop->y, $this->crop->z + 0.5, $this->speedModifier);
	}

	public function stop() : void{
		$this->mob->getNavigation()->stop();
		$this->ate = false;
	}

	public function tick() : void{
		$position = $this->mob->getPosition();
		$dx = $this->crop->x + 0.5 - $position->x;
		$dz = $this->crop->z + 0.5 - $position->z;
		if (($dx ** 2) + ($dz ** 2) <= self::EAT_DISTANCE_SQUARED) {
			$world = $this->mob->getWorld();
			$block = $world->getBlock($this->crop);
			if ($block instanceof Carrot) {
				$world->setBlock($this->crop, $block->setAge(0));
			}
			$this->ate = true;
		}
	}

	private function findCrop() : ?Vector3{
		foreach (NearestBlockFinder::find($this->mob->getWorld(), $this->mob->getPosition(), $this->searchRange, self::SEARCH_HEIGHT, BlockTypeIds::CARROTS) as $position) {
			if ($this->isGrown($position)) {
				return $position;
			}
		}

		return null;
	}

	private function isGrown(Vector3 $position) : bool{
		$block = $this->mob->getWorld()->getBlock($position);

		return $block instanceof Carrot && $block->getAge() === Carrot::MAX_AGE;
	}
}