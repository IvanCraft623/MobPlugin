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

namespace IvanCraft623\MobPlugin\entity;

use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use function array_keys;
use function count;

/**
 * Who a mob flocks with: a leader lists its followers, a follower knows its leader.
 */
final class FlockMembership{

	/** A leader takes followers while it has no more than this many */
	private int $limit;

	private bool $loner;

	private int $leaderId = -1;

	/** @var true[] id => true */
	private array $followerIds = [];

	private Vector3 $heading;

	private float $speedModifier = 1.0;

	/**
	 * @phpstan-param PathfinderMob&Flocking $owner
	 */
	public function __construct(
		private PathfinderMob&Flocking $owner,
		FlockingParameters $parameters,
		Random $random
	){
		$this->loner = $parameters->lonerChance > $random->nextFloat();
		$this->limit = $parameters->highFlockLimit > $parameters->lowFlockLimit ?
			$parameters->lowFlockLimit + $random->nextBoundedInt($parameters->highFlockLimit - $parameters->lowFlockLimit) :
			$parameters->lowFlockLimit;
		$this->heading = new Vector3(0, 0, 0);
	}

	public function isLoner() : bool{
		return $this->loner;
	}

	public function isLeader() : bool{
		return count($this->followerIds) !== 0;
	}

	public function isFollower() : bool{
		return $this->leaderId !== -1;
	}

	public function hasRoom() : bool{
		return count($this->followerIds) <= $this->limit;
	}

	/**
	 * The leader this mob follows, if it still lists it.
	 *
	 * @phpstan-return (PathfinderMob&Flocking)|null
	 */
	public function getLeader() : ?PathfinderMob{
		if ($this->leaderId === -1) {
			return null;
		}

		$leader = $this->owner->getWorld()->getEntity($this->leaderId);
		if ($leader instanceof PathfinderMob && $leader instanceof Flocking && $leader->isAlive() &&
			isset($leader->getFlockMembership()->followerIds[$this->owner->getId()])
		) {
			return $leader;
		}

		$this->leaderId = -1;
		return null;
	}

	/**
	 * Resolves the followers, dropping the ones that are gone.
	 *
	 * @phpstan-return (PathfinderMob&Flocking)[]
	 */
	public function getFollowers() : array{
		$world = $this->owner->getWorld();
		$followers = [];
		foreach (array_keys($this->followerIds) as $id) {
			$follower = $world->getEntity($id);
			if ($follower instanceof PathfinderMob && $follower instanceof Flocking && $follower->isAlive()) {
				$followers[] = $follower;
			} else {
				unset($this->followerIds[$id]);
			}
		}

		return $followers;
	}

	/**
	 * Mobs within $radius that could flock with this one.
	 *
	 * @phpstan-return \Generator<int, PathfinderMob&Flocking>
	 */
	public function findMates(float $radius) : \Generator{
		foreach ($this->owner->getSensing()->getNearbyLiving($radius, $radius) as $candidate) {
			if ($candidate !== $this->owner && $candidate instanceof PathfinderMob && $candidate instanceof Flocking &&
				$candidate->isAlive() && $candidate->canFlockNow() && $this->owner->canFlockWith($candidate) &&
				$this->owner->getPosition()->distanceSquared($candidate->getPosition()) <= $radius ** 2
			) {
				yield $candidate;
			}
		}
	}

	public function countFollowers() : int{
		return count($this->followerIds);
	}

	/**
	 * @phpstan-param PathfinderMob&Flocking $leader
	 */
	public function follow(PathfinderMob $leader) : void{
		$this->leaderId = $leader->getId();
		$leader->getFlockMembership()->followerIds[$this->owner->getId()] = true;
	}

	public function canMerge() : bool{
		return count($this->followerIds) < $this->limit;
	}

	public function canAbsorb(FlockMembership $other) : bool{
		return count($this->followerIds) + count($other->followerIds) <= $this->limit;
	}

	/**
	 * @phpstan-param PathfinderMob&Flocking $other
	 */
	public function absorb(PathfinderMob $other) : void{
		foreach ($other->getFlockMembership()->getFollowers() as $follower) {
			$follower->getFlockMembership()->leave();
			$follower->getFlockMembership()->follow($this->owner);
		}
		$other->getFlockMembership()->follow($this->owner);
	}

	public function leave() : void{
		if ($this->leaderId !== -1) {
			$leader = $this->owner->getWorld()->getEntity($this->leaderId);
			if ($leader instanceof Flocking) {
				unset($leader->getFlockMembership()->followerIds[$this->owner->getId()]);
			}
		}
		$this->leaderId = -1;
		$this->heading = new Vector3(0, 0, 0);
		$this->speedModifier = 1.0;
	}

	/**
	 * Unit vector set by the leader.
	 */
	public function getHeading() : Vector3{
		return $this->heading;
	}

	public function getSpeedModifier() : float{
		return $this->speedModifier;
	}

	public function steer(Vector3 $heading, float $speedModifier) : void{
		$this->heading = $heading;
		$this->speedModifier = $speedModifier;
	}
}