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

/**
 * How a mob flocks, as its vanilla flocking component tunes it.
 */
final class FlockingParameters{

	/**
	 * @param float $influenceRadius        blocks within which flock mates are found
	 * @param float $speed                  blocks per tick the follower steering aims for
	 * @param float $innerCohesionThreshold distance from the centre past which a follower is pulled in
	 * @param float $blockDistance          how far ahead blocks are looked for
	 * @param bool  $staysBelowSurface      never steers up out of the water
	 * @param bool  $useCenterOfMass        pull towards the flock's centre instead of the leader
	 * @param int   $lowFlockLimit          the limit of followers per leader is rolled in [low, high)
	 */
	public function __construct(
		public readonly float $influenceRadius,
		public readonly float $speed,
		public readonly float $separationWeight,
		public readonly float $separationThreshold,
		public readonly float $cohesionWeight,
		public readonly float $innerCohesionThreshold,
		public readonly float $goalWeight,
		public readonly float $blockWeight,
		public readonly float $blockDistance,
		public readonly bool $staysBelowSurface,
		public readonly bool $useCenterOfMass,
		public readonly int $lowFlockLimit,
		public readonly int $highFlockLimit,
		public readonly float $lonerChance
	){}

	public function getJoinReach() : float{
		return $this->influenceRadius * 1.5;
	}

	/**
	 * How far from its leader a follower may get before it is dropped.
	 */
	public function getKeepReach() : float{
		return $this->influenceRadius * 2 + 0.5;
	}
}