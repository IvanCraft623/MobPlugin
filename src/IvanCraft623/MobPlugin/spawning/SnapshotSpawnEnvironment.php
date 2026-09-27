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

namespace IvanCraft623\MobPlugin\spawning;

use IvanCraft623\MobPlugin\entity\MobCategory;
use pocketmine\world\World;

/**
 * SpawnEnvironment backed by one SpawnCandidateSnapshot — evaluation never touches a
 * live World. Queries are position-relative: they answer for the snapshot's single
 * frozen candidate position. Out-of-range block queries resolve to air.
 */
final class SnapshotSpawnEnvironment implements SpawnEnvironment{
	public function __construct(
		private SpawnCandidateSnapshot $snapshot
	){}

	public function getBiomeId() : int{
		return $this->snapshot->biomeId;
	}

	public function getSurfaceY() : int{
		return $this->snapshot->surfaceY;
	}

	public function getLight() : int{
		return $this->snapshot->light;
	}

	public function getBlockTypeId() : int{
		return $this->snapshot->blockTypeId;
	}

	public function getBelowBlockTypeId() : int{
		return $this->snapshot->blockUnderTypeId;
	}

	/**
	 * Same-identifier entity count in the candidate's own band (surface/cave): Bedrock
	 * density-limit counts are band-scoped, and the candidate's band is fixed by its
	 * position relative to the column surface.
	 */
	public function countNearby(string $identifier) : int{
		return ($this->snapshot->densityCounts[$identifier] ?? null)?->get($this->getBand()) ?? 0;
	}

	public function getTime() : int{
		return $this->snapshot->time;
	}

	public function getTimeOfDay() : int{
		return $this->snapshot->time % World::TIME_FULL;
	}

	/**
	 * Entity count of a MobCategory in the candidate's band, for the Bedrock population
	 * control caps (not part of SpawnEnvironment).
	 */
	public function countNearbyCategory(MobCategory $category) : int{
		return ($this->snapshot->populationCounts[$category->value] ?? null)?->get($this->getBand()) ?? 0;
	}

	private function getBand() : SpawnBand{
		return SpawnBand::fromPosition($this->snapshot->y, $this->snapshot->surfaceY);
	}
}
