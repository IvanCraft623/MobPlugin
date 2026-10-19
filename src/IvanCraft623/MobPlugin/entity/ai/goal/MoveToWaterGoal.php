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

use IvanCraft623\MobPlugin\entity\ai\utils\NearestBlockFinder;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Vector3;

class MoveToWaterGoal extends Goal {

	/** Average ticks between searches */
	private const SEARCH_INTERVAL = 10;

	protected Vector3 $water;

	public function __construct(
		protected PathfinderMob $entity,
		protected float $speedModifier,
		protected int $searchRange = 16,
		protected int $searchHeight = 5
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		if (!$this->entity->isOnGround() ||
			$this->entity->isInWater() ||
			$this->entity->getRandom()->nextBoundedInt($this->reducedTickDelay(self::SEARCH_INTERVAL)) !== 0
		) {
			return false;
		}

		$water = $this->findWater();
		if ($water === null) {
			return false;
		}

		$this->water = $water;

		return true;
	}

	public function canContinueToUse() : bool{
		return !$this->entity->isInWater() && !$this->entity->getNavigation()->isDone();
	}

	public function start() : void{
		$this->entity->getNavigation()->moveToXYZ($this->water->x + 0.5, $this->water->y, $this->water->z + 0.5, $this->speedModifier);
	}

	public function stop() : void{
		$this->entity->getNavigation()->stop();
	}

	protected function findWater() : ?Vector3{
		$world = $this->entity->getWorld();
		foreach (NearestBlockFinder::find($world, $this->entity->getPosition(), $this->searchRange, $this->searchHeight, BlockTypeIds::WATER) as $water) {
			$x = (int) $water->x;
			$y = (int) $water->y;
			$z = (int) $water->z;
			// Needs to be at least 2 blocks deep, a single block is no place to stay
			if ($world->getBlockAt($x, $y + 1, $z)->getTypeId() === BlockTypeIds::WATER ||
				$world->getBlockAt($x, $y - 1, $z)->getTypeId() === BlockTypeIds::WATER
			) {
				return $water;
			}
		}

		return null;
	}
}
