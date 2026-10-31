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
use IvanCraft623\Pathfinder\Path;
use pocketmine\block\Door;
use pocketmine\math\Vector3;
use function floor;
use function min;

abstract class DoorInteractGoal extends Goal {

	protected Vector3 $doorPosition;

	public function __construct(protected PathfinderMob $entity) {
		if (!$entity->getNavigation() instanceof GroundPathNavigation) {
			throw new \InvalidArgumentException("This entity type does not support path navigation");
		}
	}

	protected function isDoorOpen() : bool {
		$block = $this->entity->getWorld()->getBlock($this->doorPosition);

		return $block instanceof Door && $block->isOpen();
	}

	public function canUse() : bool {
		if (!$this->entity->isCollidedHorizontally) {
			return false;
		}

		$path = $this->entity->getNavigation()->getPath();
		if ($path === null || $path->isDone()) {
			return false;
		}

		$doorPosition = $this->findDoor($path);
		if ($doorPosition === null) {
			return false;
		}

		$this->doorPosition = $doorPosition;

		return true;
	}

	private function findDoor(Path $path) : ?Vector3 {
		$world = $this->entity->getWorld();
		$location = $this->entity->getLocation();

		$length = min($path->getNodeCount(), $path->getNextNodeIndex() + 2);
		for ($i = 0; $i < $length; $i++) {
			$node = $path->getNode($i);
			$x = (int) $node->x;
			$y = (int) $node->y + 1;
			$z = (int) $node->z;

			$dx = $x - $location->x;
			$dz = $z - $location->z;
			if ($dx * $dx + $dz * $dz > 2.25) {
				continue;
			}

			$block = $world->getBlockAt($x, $y, $z);
			if (!$block instanceof Door) {
				continue;
			}

			// The door only counts if its panel is between the entity and the next node.
			$next = $path->getNode(min($i + 1, $path->getNodeCount() - 1));
			if ($block->calculateIntercept(
				new Vector3($location->x, $y + 0.5, $location->z),
				new Vector3($next->x + 0.5, $y + 0.5, $next->z + 0.5)
			) !== null) {
				return new Vector3($x, $y, $z);
			}
		}

		// The door the entity is standing in.
		$x = (int) floor($location->x);
		$y = (int) floor($location->y) + 1;
		$z = (int) floor($location->z);

		return $world->getBlockAt($x, $y, $z) instanceof Door ? new Vector3($x, $y, $z) : null;
	}

	public function requiresUpdateEveryTick() : bool {
		return true;
	}
}
