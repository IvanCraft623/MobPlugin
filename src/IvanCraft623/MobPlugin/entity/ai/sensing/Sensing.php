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

namespace IvanCraft623\MobPlugin\entity\ai\sensing;

use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\entity\Entity;

class Sensing {

	private Mob $mob;

	private array $seen = [];

	private array $unseen = [];

	public function __construct(Mob $mob) {
		$this->mob = $mob;
	}

	public function tick() : void {
		$this->seen = [];
		$this->unseen = [];
	}

	public function canSee(Entity $entity) : bool {
		if (isset($this->seen[$entity->getId()])) {
			return true;
		}
		if (isset($this->unseen[$entity->getId()])) {
			return false;
		}
		$canSee = $this->mob->canSee($entity);
		if ($canSee) {
			$this->seen[$entity->getId()] = $entity;
		} else {
			$this->unseen[$entity->getId()] = $entity;
		}
		return $canSee;
	}
}
