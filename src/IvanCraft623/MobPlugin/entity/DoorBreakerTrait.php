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

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\entity\ai\goal\BreakDoorGoal;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use function mt_rand;

/**
 * Implements DoorBreaker: a share of the spawned mobs break doors, which is saved as a component group.
 *
 * @phpstan-require-extends PathfinderMob
 * @phpstan-require-implements DoorBreaker
 */
trait DoorBreakerTrait{

	private const COMPONENT_GROUP_CAN_BREAK_DOORS = "minecraft:can_break_doors";

	private bool $canBreakDoors = false;

	private ?BreakDoorGoal $breakDoorGoal = null;

	public function canBreakDoors() : bool{
		return $this->canBreakDoors;
	}

	/**
	 * @return $this
	 */
	public function setCanBreakDoors(bool $value = true) : self{
		if ($this->canBreakDoors !== $value) {
			$this->canBreakDoors = $value;

			$this->navigation->getRules()->canOpenDoors = $value;
			$this->breakDoorGoal ??= new BreakDoorGoal($this, World::DIFFICULTY_HARD);
			if ($value) {
				$this->goalSelector->addGoal(1, $this->breakDoorGoal);
			} else {
				$this->goalSelector->removeGoal($this->breakDoorGoal);
			}
		}

		return $this;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		if ($nbt->count() !== 0) {
			$this->setCanBreakDoors($this->componentGroups->has(self::COMPONENT_GROUP_CAN_BREAK_DOORS));
		} else { // First spawn
			$this->setCanBreakDoors(mt_rand(1, 10) === 1);
		}
	}

	public function saveNBT() : CompoundTag{
		//TODO: this is a hack and we are only saving the minimum for our implementation.
		if ($this->canBreakDoors) {
			$this->componentGroups->add(self::COMPONENT_GROUP_CAN_BREAK_DOORS);
		}

		return parent::saveNBT();
	}

	protected function destroyCycles() : void{
		$this->breakDoorGoal = null;
		parent::destroyCycles();
	}
}
