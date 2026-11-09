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

use IvanCraft623\MobPlugin\entity\ai\utils\FlockSteering;
use IvanCraft623\MobPlugin\entity\Flocking;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;
use function sprintf;

/**
 * Prunes a flock, merges it with nearby ones and has its leader steer the followers.
 */
class ManageFlockGoal extends Goal {

	private const CHECK_INTERVAL = 10;
	private const STEER_INTERVAL = 2;

	private int $checkTimer = 0;

	private int $steerTimer = 0;

	private ?Vector3 $lastPosition = null;

	/**
	 * @phpstan-param PathfinderMob&Flocking $mob
	 */
	public function __construct(
		private PathfinderMob&Flocking $mob
	) {
	}

	public function canUse() : bool{
		return true;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$membership = $this->mob->getFlockMembership();
		if (++$this->checkTimer >= self::CHECK_INTERVAL) {
			$this->checkTimer = 0;
			if ($membership->isLeader()) {
				$this->pruneFollowers();
				$this->mergeNearbyFlock();
			} else {
				$this->checkLeader();
			}
		}

		if (!$membership->isLeader()) {
			$this->lastPosition = null;
		} elseif (++$this->steerTimer >= self::STEER_INTERVAL) {
			$this->steerTimer = 0;
			$position = $this->mob->getPosition();
			//Measured from where it went, since some goals move a mob without giving it motion
			$velocity = $this->lastPosition === null ? Vector3::zero() : $position->subtractVector($this->lastPosition)->divide(self::STEER_INTERVAL);
			$this->lastPosition = $position;
			FlockSteering::steer($this->mob, $membership->getFollowers(), $velocity);
		}
	}

	public function getCurrentDebugInfo() : ?string{
		$membership = $this->mob->getFlockMembership();
		if ($membership->isLeader()) {
			return "leader of " . $membership->countFollowers();
		}

		$leader = $membership->getLeader();
		if ($leader !== null) {
			return sprintf(
				"follows #%d, %.1f blocks, x%.1f",
				$leader->getId(),
				$this->mob->getPosition()->distance($leader->getPosition()),
				$membership->getSpeedModifier()
			);
		}

		return $membership->isLoner() ? "free, loner" : "free";
	}

	private function checkLeader() : void{
		$membership = $this->mob->getFlockMembership();
		if (!$membership->isFollower()) {
			return;
		}

		$leader = $membership->getLeader();
		if ($leader === null || $this->mob->getPosition()->distanceSquared($leader->getPosition()) > $this->mob->getFlockingParameters()->getKeepReach() ** 2) {
			$membership->leave();
		}
	}

	private function pruneFollowers() : void{
		$membership = $this->mob->getFlockMembership();
		$reach = $this->mob->getFlockingParameters()->getKeepReach();
		foreach ($membership->getFollowers() as $follower) {
			if ($this->mob->getPosition()->distanceSquared($follower->getPosition()) > $reach ** 2) {
				$follower->getFlockMembership()->leave();
			}
		}
	}

	/**
	 * Takes in a nearby flock when both fit in one, so pairs that formed apart grow into a school.
	 */
	private function mergeNearbyFlock() : void{
		$membership = $this->mob->getFlockMembership();
		if (!$membership->canMerge()) {
			return;
		}
		foreach ($membership->findMates($this->mob->getFlockingParameters()->getJoinReach()) as $mate) {
			if ($mate->getFlockMembership()->isLeader() && $membership->canAbsorb($mate->getFlockMembership())) {
				$membership->absorb($mate);
				return;
			}
		}
	}
}