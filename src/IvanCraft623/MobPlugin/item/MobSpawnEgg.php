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

namespace IvanCraft623\MobPlugin\item;

use IvanCraft623\MobPlugin\event\MobSpawnCause;
use IvanCraft623\MobPlugin\event\MobSpawnEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class MobSpawnEgg extends SpawnEgg{

	/**
	 * @phpstan-param class-string<Entity> $entityClass
	 */
	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private readonly string $entityClass
	){
		parent::__construct($identifier, $name);
	}

	protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
		$entity = new $this->entityClass(Location::fromObject($pos, $world, $yaw, $pitch));
		(new MobSpawnEvent($entity, MobSpawnCause::SPAWN_EGG))->call();

		return $entity;
	}
}