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
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\entity\EntityEvent;

/**
 * Called when an entity is about to despawn by its despawn rule. Cancelling it only keeps
 * the entity until its next check.
 *
 * @phpstan-extends EntityEvent<Entity>
 */
class EntityNaturalDespawnEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Entity $entity,
		private readonly NaturalDespawnCause $cause
	){
		$this->entity = $entity;
	}

	public function getCause() : NaturalDespawnCause{
		return $this->cause;
	}
}