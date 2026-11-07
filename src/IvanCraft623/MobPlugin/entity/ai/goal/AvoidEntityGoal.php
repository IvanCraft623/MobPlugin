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

use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\ai\utils\DefaultPositionGenerator;
use IvanCraft623\MobPlugin\entity\PathfinderMob;

use pocketmine\block\Water;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use const INF;

/**
 * Runs away from the nearest entity of the given types that gets too close, faster when it is very close.
 */
class AvoidEntityGoal extends Goal {

	private const POSITION_ATTEMPTS = 10;
	private const SPRINT_DISTANCE_SQUARED = 49;
	private const CHECK_INTERVAL = 4;

	protected ?Living $toAvoid = null;

	protected ?Vector3 $destination = null;

	private TargetingConditions $conditions;

	private int $checkTimer = 0;

	/**
	 * @phpstan-param class-string<Living>[] $avoidTypes
	 */
	public function __construct(
		protected PathfinderMob $mob,
		protected array $avoidTypes,
		protected float $maxDistance,
		protected float $walkSpeedModifier,
		protected float $sprintSpeedModifier,
		protected bool $stayInWater = false
	) {
		$this->conditions = new TargetingConditions($maxDistance);
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		if (--$this->checkTimer > 0) {
			return false;
		}
		$this->checkTimer = $this->reducedTickDelay(self::CHECK_INTERVAL);

		$nearest = null;
		$nearestDistanceSquared = INF;
		$origin = $this->mob->getPosition();
		foreach ($this->mob->getSensing()->getNearbyLiving($this->maxDistance) as $candidate) {
			$distanceSquared = $origin->distanceSquared($candidate->getPosition());
			if ($distanceSquared < $nearestDistanceSquared && $this->isAvoided($candidate) && $this->conditions->test($this->mob, $candidate)) {
				$nearest = $candidate;
				$nearestDistanceSquared = $distanceSquared;
			}
		}
		if ($nearest === null) {
			return false;
		}

		$destination = $this->findDestination($nearest);
		if ($destination === null) {
			return false;
		}

		$this->toAvoid = $nearest;
		$this->destination = $destination;

		return true;
	}

	private function isAvoided(Living $candidate) : bool{
		foreach ($this->avoidTypes as $type) {
			if ($candidate instanceof $type) {
				return true;
			}
		}

		return false;
	}

	protected function findDestination(Living $toAvoid) : ?Vector3{
		$world = $this->mob->getWorld();
		$awayFrom = $toAvoid->getPosition();
		$currentDistanceSquared = $this->mob->getPosition()->distanceSquared($awayFrom);
		for ($i = 0; $i < self::POSITION_ATTEMPTS; ++$i) {
			$position = DefaultPositionGenerator::getPosAway($this->mob, 16, 7, $awayFrom);
			if ($position === null ||
				($this->stayInWater && !$world->getBlock($position) instanceof Water) ||
				$position->distanceSquared($awayFrom) <= $currentDistanceSquared
			) {
				continue;
			}

			return $position;
		}

		return null;
	}

	public function canContinueToUse() : bool{
		return !$this->mob->getNavigation()->isDone();
	}

	public function start() : void{
		if ($this->destination !== null) {
			$this->mob->getNavigation()->moveToXYZ($this->destination->x, $this->destination->y, $this->destination->z, $this->walkSpeedModifier);
		}
	}

	public function tick() : void{
		if ($this->toAvoid === null || $this->destination === null) {
			return;
		}

		$sprinting = $this->mob->getPosition()->distanceSquared($this->toAvoid->getPosition()) < self::SPRINT_DISTANCE_SQUARED;
		$speedModifier = $sprinting ? $this->sprintSpeedModifier : $this->walkSpeedModifier;
		if ($this->mob->getNavigation()->getSpeedModifier() !== $speedModifier) {
			$this->mob->getNavigation()->moveToXYZ($this->destination->x, $this->destination->y, $this->destination->z, $speedModifier);
		}
	}

	public function stop() : void{
		$this->toAvoid = null;
		$this->destination = null;
	}
}
