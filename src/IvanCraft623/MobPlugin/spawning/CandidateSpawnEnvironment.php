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

use pocketmine\world\World;

/**
 * SpawnEnvironment over one sampled position and its census counts — conditions never
 * touch a live World.
 */
final class CandidateSpawnEnvironment implements SpawnEnvironment{
	public function __construct(
		private readonly SpawnPosition $position,
		private readonly SpawnCounts $counts
	){}

	public function getBiomeId() : int{
		return $this->position->biomeId;
	}

	public function getSurfaceY() : int{
		return $this->position->groundY;
	}

	public function getLight() : int{
		return $this->position->light;
	}

	public function getBlockTypeId() : int{
		return $this->position->feetTypeId;
	}

	public function getBelowBlockTypeId() : int{
		return $this->position->belowTypeId;
	}

	/**
	 * Same-identifier count in the position's own band: Bedrock density limits are
	 * band-scoped.
	 */
	public function countNearby(string $identifier) : int{
		return $this->counts->identifier($identifier, $this->position->band);
	}

	public function getTime() : int{
		return $this->position->time;
	}

	public function getTimeOfDay() : int{
		return $this->position->time % World::TIME_FULL;
	}
}
