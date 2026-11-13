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

namespace IvanCraft623\MobPlugin\entity\ai\navigation\locomotion;

use IvanCraft623\MobPlugin\entity\ai\navigation\TraversalRules;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\pathtype\BlockClassifier;
use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use function array_slice;
use function floor;

class GroundLocomotion extends Locomotion{
	public function createEvaluator() : NodeEvaluator{
		return new WalkNodeEvaluator();
	}

	public function canFollowPath(PathfinderMob $mob, TraversalRules $rules) : bool{
		return $mob->isOnGround() || self::isInLiquid($mob);
	}

	public function pathPosition(PathfinderMob $mob, TraversalRules $rules) : Vector3{
		$position = $mob->getPosition();
		return new Vector3($position->getX(), $this->getSurfaceY($mob, $rules), $position->getZ());
	}

	public function isStableDestination(PathfinderMob $mob, Vector3 $position) : bool{
		return $mob->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->isSolid();
	}

	public function isGroundBased() : bool{
		return true;
	}

	public function wantedY(PathfinderMob $mob, BlockGetter $blockGetter, Vector3 $position) : float{
		$below = $mob->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ());
		return $below->getTypeId() === BlockTypeIds::AIR ? $position->y : BlockClassifier::getFloorLevel($blockGetter, $position);
	}

	/**
	 * Moves a target in the air down to the ground, and one inside solid blocks up out of them.
	 */
	public function adjustTarget(PathfinderMob $mob, Vector3 $target) : Vector3{
		$world = $mob->getWorld();
		if ($world->getBlock($target)->getTypeId() === BlockTypeIds::AIR) {
			$current = $target->down();
			while ($current->y > $world->getMinY() && $world->getBlock($current)->getTypeId() === BlockTypeIds::AIR) {
				$current = $current->down();
			}
			if ($current->getY() > $world->getMinY()) {
				return $current->up();
			}
			while ($current->getY() < $world->getMaxY() && $world->getBlock($current)->getTypeId() === BlockTypeIds::AIR) {
				$current = $current->up();
			}
			$target = $current;
		}
		if (!$world->getBlock($target)->isSolid()) {
			return $target;
		}
		$current = $target->up();
		while ($current->getY() < $world->getMaxY() && $world->getBlock($current)->isSolid()) {
			$current = $current->up();
		}
		return $current;
	}

	public function adjustPath(PathfinderMob $mob, Path $path, TraversalRules $rules) : Path{
		$path = parent::adjustPath($mob, $path, $rules);

		if (!$rules->avoidSun) {
			return $path;
		}
		$position = $mob->getPosition();
		$world = $mob->getWorld();
		if ($world->getRealBlockSkyLightAt((int) floor($position->x), (int) floor($position->y + 0.5), (int) floor($position->z)) >= 15) {
			return $path;
		}
		for ($i = 0; $i < $path->getNodeCount(); ++$i) {
			$node = $path->getNode($i);
			if ($world->getRealBlockSkyLightAt($node->x(), $node->y(), $node->z()) >= 15) {
				return $path->withNodes(array_slice($path->getNodes(), 0, $i));
			}
		}

		return $path;
	}

	protected function getSurfaceY(PathfinderMob $mob, TraversalRules $rules) : int{
		$position = $mob->getPosition();
		if ($mob->isInWater() && $rules->canFloat) {
			$y = (int) $position->getY();
			$world = $mob->getWorld();
			$block = $world->getBlock($position);
			$distance = 0;
			while ($block instanceof Water) {
				$block = $world->getBlockAt((int) floor($position->x), ++$y, (int) floor($position->z));
				if (++$distance > 16) {
					return (int) $position->getY();
				}
			}
			return $y;
		}
		return (int) floor($position->y + 0.5);
	}
}