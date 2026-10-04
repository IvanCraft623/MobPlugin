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

namespace IvanCraft623\MobPlugin\event;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityEvent;

/**
 * Called when a mob is about to be spawned to the world for the first time. Unlike
 * PocketMine's EntitySpawnEvent it is not called for mobs loaded from storage, and it
 * says why the mob spawned.
 *
 * @phpstan-extends EntityEvent<Entity>
 */
class MobSpawnEvent extends EntityEvent{

	public function __construct(
		Entity $entity,
		private readonly MobSpawnCause $cause
	){
		$this->entity = $entity;
	}

	public function getCause() : MobSpawnCause{
		return $this->cause;
	}
}