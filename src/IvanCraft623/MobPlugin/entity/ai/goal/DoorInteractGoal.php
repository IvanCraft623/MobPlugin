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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\ai\navigation\GroundPathNavigation;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Door;
use pocketmine\math\Vector3;
use function min;

abstract class DoorInteractGoal extends Goal {

	protected Vector3 $doorPosition;

	protected bool $isValidDoor = false;
	protected bool $shouldStop = false;

	private float $initialXOffset = 0.0;
	private float $initialZOffset = 0.0;

	public function __construct(protected PathfinderMob $entity) {
		if (!$entity->getNavigation() instanceof GroundPathNavigation) {
			throw new \InvalidArgumentException("This entity type does not support path navigation");
		}
	}

	protected function isDoorOpen() : bool {
		if (!$this->isValidDoor) {
			return false;
		}

		$block = $this->entity->getWorld()->getBlock($this->doorPosition);
		if (!$block instanceof Door) {
			$this->isValidDoor = false;
			return false;
		}

		return $block->isOpen();
	}

	protected function setDoorOpen(bool $open) : void {
		if (!$this->isValidDoor) {
			return;
		}

		$world = $this->entity->getWorld();
		$block = $world->getBlock($this->doorPosition);
		if ($block instanceof Door) {
			$world->setBlock($this->doorPosition, $block->setOpen($open));
		}
	}

	public function canUse() : bool {
		if (!$this->entity->getNavigation() instanceof GroundPathNavigation) {
			return false;
		}
		if (!$this->entity->isCollidedHorizontally) {
			return false;
		}

		$path = $this->entity->getNavigation()->getPath();
		if ($path === null || $path->isDone()) {
			return false;
		}

		$length = min($path->getNodeCount(), $path->getNextNodeIndex() + 2);
		for ($i = 0; $i < $length; $i++) {
			$node = $path->getNode($i);
			$doorPos = new Vector3((int) $node->x, (int) $node->y + 1, (int) $node->z);
			if ($this->entity->getPosition()->distanceSquared($doorPos) <= 2.25) {
				$block = $this->entity->getWorld()->getBlock($doorPos);
				if ($block instanceof Door && $block->getTypeId() !== BlockTypeIds::IRON_DOOR) {
					$this->doorPosition = $doorPos;
					$this->isValidDoor = true;
					return true;
				}
			}
		}

		$doorPos = $this->entity->getPosition()->add(0, 1, 0);
		$block = $this->entity->getWorld()->getBlock($doorPos);
		if ($block instanceof Door && $block->getTypeId() !== BlockTypeIds::IRON_DOOR) {
			$this->doorPosition = $doorPos;
			$this->isValidDoor = true;
			return true;
		}

		return false;
	}

	public function canContinueToUse() : bool {
		return !$this->shouldStop;
	}

	public function start() : void {
		$this->shouldStop = false;
		$this->initialXOffset = $this->doorPosition->x + 0.5 - $this->entity->getPosition()->x;
		$this->initialZOffset = $this->doorPosition->z + 0.5 - $this->entity->getPosition()->z;
	}

	public function tick() : void {
		$currentXOffset = $this->doorPosition->x + 0.5 - $this->entity->getPosition()->x;
		$currentZOffset = $this->doorPosition->z + 0.5 - $this->entity->getPosition()->z;
		$dot = $this->initialXOffset * $currentXOffset + $this->initialZOffset * $currentZOffset;

		if ($dot < 0.0) {
			$this->shouldStop = true;
		}
	}

	public function stop() : void {
		unset($this->doorPosition);
	}

	public function requiresUpdateEveryTick() : bool {
		return true;
	}
}
