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

namespace IvanCraft623\MobPlugin\spawning\spawner;

use pocketmine\entity\Entity;

/**
 * What a test factory returns: never constructed, and spawnToAll() does nothing.
 */
final class SpawnedEntityStub extends CensusTestEntity{
	public static function create() : self{
		$entity = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Entity::class, "closeInFlight"))->setValue($entity, true);

		return $entity;
	}

	public static function getNetworkTypeId() : string{
		return "test:spawned";
	}

	public function spawnToAll() : void{
	}
}
