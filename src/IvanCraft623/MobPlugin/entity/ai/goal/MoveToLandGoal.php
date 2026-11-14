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

use IvanCraft623\MobPlugin\entity\ai\utils\LandPositionGenerator;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;

/**
 * Heads for dry land when in water.
 */
class MoveToLandGoal extends MoveToBlockGoal {

	protected Vector3 $land;

	/**
	 * @param int $searchRange  blocks around it to look for land
	 * @param int $searchHeight blocks above and below it to look for land
	 */
	public function __construct(
		PathfinderMob $entity,
		protected float $speedModifier,
		protected int $searchRange = 16,
		protected int $searchHeight = 5
	) {
		parent::__construct($entity);
	}

	protected function canSearch() : bool{
		return $this->entity->isInWater();
	}

	protected function findBlock() : bool{
		$land = LandPositionGenerator::getPos($this->entity, $this->searchRange, $this->searchHeight);
		if ($land === null) {
			return false;
		}

		$this->land = $land;

		return true;
	}

	public function canContinueToUse() : bool{
		return !$this->entity->getNavigation()->isDone();
	}

	public function start() : void{
		$this->entity->getNavigation()->moveToXYZ($this->land->x, $this->land->y, $this->land->z, $this->speedModifier);
	}

	public function stop() : void{
		$this->entity->getNavigation()->stop();
	}
}
