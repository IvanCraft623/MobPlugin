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

use Closure;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\math\Vector3;

/**
 * When out in the open, walks to a spot with no sky above that the mob doesn't mind being on.
 */
class FindCoverGoal extends Goal {

	private const SEARCH_RANGE = 10; //blocks, each way
	private const SEARCH_HEIGHT = 3; //blocks, each way

	protected Vector3 $coverPosition;

	private int $nextTick = 0; //server tick before which it won't look for cover

	/**
	 * @param int                     $cooldown  ticks after it stops before it looks for cover again
	 * @param (Closure() : bool)|null $condition whether it may look for cover now, null if always
	 */
	public function __construct(
		protected PathfinderMob $entity,
		protected float $speedModifier,
		private int $cooldown = 0,
		private ?Closure $condition = null
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		if (
			$this->entity->getServer()->getTick() < $this->nextTick ||
			($this->condition !== null && !($this->condition)()) ||
			!$this->isExposed()
		) {
			return false;
		}

		$pos = $this->getCoverPosition();
		if ($pos === null) {
			return false;
		}

		$this->coverPosition = $pos;
		return true;
	}

	public function canContinueToUse() : bool{
		return !$this->entity->getNavigation()->isDone();
	}

	public function start() : void{
		$this->entity->getNavigation()->moveToXYZ(
			$this->coverPosition->x,
			$this->coverPosition->y,
			$this->coverPosition->z,
			$this->speedModifier
		);
	}

	public function stop() : void{
		parent::stop();

		unset($this->coverPosition);
		$this->nextTick = $this->entity->getServer()->getTick() + $this->cooldown;
	}

	/**
	 * Whether there is sky above the mob.
	 */
	protected function isExposed() : bool{
		return Utils::isSkyVisible($this->entity->getWorld(), $this->entity->getPosition());
	}

	protected function getCoverPosition() : ?Vector3{
		$random = $this->entity->getRandom();
		$entityPosition = $this->entity->getPosition();
		$world = $this->entity->getWorld();

		for ($i = 0; $i < 10; ++$i) {
			$p = $entityPosition->add(
				$random->nextBoundedInt(self::SEARCH_RANGE * 2) - self::SEARCH_RANGE,
				$random->nextBoundedInt(self::SEARCH_HEIGHT * 2) - self::SEARCH_HEIGHT,
				$random->nextBoundedInt(self::SEARCH_RANGE * 2) - self::SEARCH_RANGE
			);
			if (!Utils::isSkyVisible($world, $p) && $this->entity->getWalkTargetValue($p) < 0) {
				return $p->floor()->add(0.5, 0, 0.5);
			}
		}

		return null;
	}
}