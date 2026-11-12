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

namespace IvanCraft623\MobPlugin\memory;

use pocketmine\entity\Entity;
use WeakReference;

/**
 * The entity something happened with, and when.
 */
final class Memory{

	/** @phpstan-var WeakReference<Entity> */
	private WeakReference $entity;

	public function __construct(Entity $entity, private readonly int $tick){
		$this->entity = WeakReference::create($entity);
	}

	/**
	 * The entity, or null if it is gone.
	 */
	public function getEntity() : ?Entity{
		$entity = $this->entity->get();

		return $entity !== null && !$entity->isClosed() ? $entity : null;
	}

	/**
	 * @return int server tick it happened at
	 */
	public function getTick() : int{
		return $this->tick;
	}
}
