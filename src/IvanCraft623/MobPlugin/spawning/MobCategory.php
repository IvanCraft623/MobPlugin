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

final class MobCategory{

	public function __construct(
		public readonly string $id,
		public readonly int $surfaceCap,
		public readonly int $caveCap,
		public readonly int $despawnDistance,
		public readonly int $noDespawnDistance = 32
	){}

	public function getCap(SpawnBand $band) : int{
		return $band === SpawnBand::SURFACE ? $this->surfaceCap : $this->caveCap;
	}

	public function getDespawnDistance() : int{
		return $this->despawnDistance;
	}

	public function getNoDespawnDistance() : int{
		return $this->noDespawnDistance;
	}
}
