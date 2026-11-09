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

use IvanCraft623\MobPlugin\entity\Flocking;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;
use function floor;
use function min;
use const INF;

/**
 * Joins a nearby flock and steers where the leader's heading points. TODO: walking mobs need a path to a point ahead instead.
 */
class FollowFlockGoal extends Goal {

	private const SEARCH_INTERVAL = 6;
	/** The search backs off up to this while it finds nothing */
	private const MAX_SEARCH_INTERVAL = 24;
	/** How much of the way from its velocity to the heading the follower turns each tick */
	private const SMOOTHING = 0.22;
	/** Ticks of the mob's velocity it must be able to glide through, on its own level */
	private const GLIDE_TICKS = 6;

	private int $searchTimer = 0;

	private int $searchInterval = self::SEARCH_INTERVAL;

	/**
	 * @phpstan-param PathfinderMob&Flocking $mob
	 */
	public function __construct(
		private PathfinderMob&Flocking $mob
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		$membership = $this->mob->getFlockMembership();
		if (!$this->mob->canFlockNow() || $membership->isLeader()) {
			return false;
		}

		if ($membership->getLeader() !== null) {
			return true;
		}
		if (++$this->searchTimer < $this->reducedTickDelay($this->searchInterval)) {
			return false;
		}
		$this->searchTimer = 0;

		$joined = $this->join();
		$this->searchInterval = $joined ? self::SEARCH_INTERVAL : min($this->searchInterval * 2, self::MAX_SEARCH_INTERVAL);

		return $joined;
	}

	public function canContinueToUse() : bool{
		$membership = $this->mob->getFlockMembership();

		return $this->mob->canFlockNow() && !$membership->isLeader() && $membership->getLeader() !== null;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function stop() : void{
		$this->mob->getFlockMembership()->leave();
	}

	public function tick() : void{
		$membership = $this->mob->getFlockMembership();
		$heading = $membership->getHeading();
		if ($heading->lengthSquared() < 1.0E-8) {
			return;
		}

		$parameters = $this->mob->getFlockingParameters();
		$speedModifier = $membership->getSpeedModifier();
		$speed = $parameters->speed * $speedModifier;

		$velocity = $this->mob->getMotion();
		$position = $this->mob->getLocation();
		$wantedX = $position->x + $velocity->x + (($heading->x * $speed) - $velocity->x) * self::SMOOTHING;
		$wantedZ = $position->z + $velocity->z + (($heading->z * $speed) - $velocity->z) * self::SMOOTHING;
		$level = $position->getFloorY();
		//A mob keeps gliding, so it also holds still where its glide would take it out
		if ($this->canFlockThrough((int) floor($wantedX), $level, (int) floor($wantedZ)) &&
			$this->canFlockThrough((int) floor($position->x + $velocity->x * self::GLIDE_TICKS), $level, (int) floor($position->z + $velocity->z * self::GLIDE_TICKS))
		) {
			$wantedY = $position->y + $velocity->y + (($heading->y * $speed) - $velocity->y) * self::SMOOTHING;
			$this->mob->getMoveControl()->setWantedPosition(new Vector3($wantedX, $wantedY, $wantedZ), $speedModifier);
		}
	}

	private function canFlockThrough(int $x, int $level, int $z) : bool{
		return $this->mob->canFlockThrough($this->mob->getWorld()->getBlockAt($x, $level, $z));
	}

	private function join() : bool{
		$radius = $this->mob->getFlockingParameters()->getJoinReach();
		$bestLeader = null;
		$bestLeaderDistance = INF;
		$bestFree = null;
		$bestFreeDistance = INF;

		foreach ($this->mob->getFlockMembership()->findMates($radius) as $mate) {
			$distance = $this->mob->getPosition()->distanceSquared($mate->getPosition());
			$mateMembership = $mate->getFlockMembership();
			if ($mateMembership->isLeader()) {
				if ($mateMembership->hasRoom() && $distance < $bestLeaderDistance) {
					$bestLeader = $mate;
					$bestLeaderDistance = $distance;
				}
			} elseif (!$mateMembership->isFollower() && !$mateMembership->isLoner() && $distance < $bestFreeDistance) {
				$bestFree = $mate;
				$bestFreeDistance = $distance;
			}
		}

		$leader = $bestLeader ?? $bestFree;
		if ($leader === null) {
			return false;
		}
		$this->mob->getFlockMembership()->follow($leader);

		return true;
	}
}