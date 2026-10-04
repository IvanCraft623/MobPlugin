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

namespace IvanCraft623\MobPlugin\spawning\population;

use IvanCraft623\MobPlugin\spawning\SpawnBand;
use pocketmine\entity\Entity;

/**
 * The band each entity counts in. The spawner sets it for the mobs it places; the census
 * sets it for any other entity, from where it stands when first counted.
 */
final class EntitySpawnBands{
	/** @phpstan-var \WeakMap<Entity, SpawnBand> */
	private \WeakMap $bands;

	public function __construct(){
		$this->bands = new \WeakMap();
	}

	public function set(Entity $entity, SpawnBand $band) : void{
		$this->bands[$entity] = $band;
	}

	public function get(Entity $entity) : ?SpawnBand{
		return $this->bands[$entity] ?? null;
	}
}