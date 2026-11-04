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
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\block\FillableCauldron;
use pocketmine\block\Liquid;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use function floor;

/**
 * A way of moving: walking, flying, swimming. The hooks default to a walker's.
 */
abstract class Locomotion{
	abstract public function createEvaluator() : NodeEvaluator;

	abstract public function canFollowPath(Mob $mob, TraversalRules $rules) : bool;

	/**
	 * Where the mob is for following a path.
	 */
	abstract public function pathPosition(Mob $mob, TraversalRules $rules) : Vector3;

	abstract public function isStableDestination(Mob $mob, Vector3 $position) : bool;

	/**
	 * Costs for searches; wander targets read the mob's own map.
	 */
	public function searchCostMap(Mob $mob) : BlockPathTypeCostMap{
		return $mob->getNavigation()->getCostMap();
	}

	final public function createProfile(Mob $mob, TraversalRules $rules) : MovementProfile{
		$size = $mob->getSize();
		return new MovementProfile(
			width: $size->getWidth(),
			height: $size->getHeight(),
			maxUpStep: $mob->getStepHeight(),
			maxFallDistance: $mob->getMaxFallDistance(),
			costMap: $this->searchCostMap($mob),
			canPassDoors: $rules->canPassDoors,
			canOpenDoors: $rules->canOpenDoors,
			canFloat: $rules->canFloat,
			canWalkOverFences: $rules->canWalkOverFences,
			eyeHeight: $size->getEyeHeight()
		);
	}

	/**
	 * Y the move control is sent to for the node at $position.
	 */
	public function wantedY(Mob $mob, BlockGetter $blockGetter, Vector3 $position) : float{
		return $position->y;
	}

	/**
	 * The target to search to, e.g. snapped to the ground.
	 */
	public function adjustTarget(Mob $mob, Vector3 $target) : Vector3{
		return $target;
	}

	/**
	 * Fits a computed path to the mob.
	 */
	public function adjustPath(Mob $mob, Path $path, TraversalRules $rules) : void{
		$world = $mob->getWorld();
		for ($i = 0; $i < $path->getNodeCount(); $i++) {
			$node = $path->getNode($i);
			if ($world->getBlockAt($node->x(), $node->y(), $node->z()) instanceof FillableCauldron) {
				$path->replaceNode($i, $node->cloneAndMove($node->x(), $node->y() + 1, $node->z()));

				$nextNode = $i + 1 < $path->getNodeCount() ? $path->getNode($i + 1) : null;
				if ($nextNode !== null && $node->y >= $nextNode->y) {
					$path->replaceNode($i + 1, $node->cloneAndMove($nextNode->x(), $node->y() + 1, $nextNode->z()));
				}
			}
		}
	}

	/**
	 * Whether the mob can skip the nodes between two points and go straight.
	 */
	public function canMoveDirectly(Mob $mob, Vector3 $from, Vector3 $to) : bool{
		return false;
	}

	public function canCutCorner(BlockPathType $pathType) : bool{
		return $pathType !== BlockPathType::DANGER_FIRE &&
			$pathType !== BlockPathType::DANGER_OTHER &&
			$pathType !== BlockPathType::WALKABLE_DOOR;
	}

	/**
	 * Advances along the path for a mob that can't follow it (e.g. airborne).
	 */
	public function advanceWithoutFollowing(Mob $mob, Path $path, Vector3 $position) : void{
		$nextPosition = $path->getNextNodeCenter();
		if ($position->y > $nextPosition->y &&
			!$mob->isOnGround() &&
			floor($position->x) === floor($nextPosition->x) &&
			floor($position->z) === floor($nextPosition->z)
		) {
			$path->advance();
		}
	}

	/**
	 * Whether the mob walks on the ground, so doors and sunlight matter to it.
	 */
	public function isGroundBased() : bool{
		return false;
	}

	public function onMoveRequested(Vector3 $target, float $speedModifier) : void{
	}

	public function onStopped() : void{
	}

	/**
	 * Called every tick with no path to follow or being computed.
	 */
	public function tickWithoutPath(Mob $mob) : void{
	}

	public static function isInLiquid(Mob $mob) : bool{
		//Water and lava don't have collision boxes, so they never show up in getCollisionBlocks().
		return $mob->isInWater() || $mob->isInLava();
	}

	protected static function isClearForMovementBetween(Mob $mob, Vector3 $from, Vector3 $to, bool $detectLiquids) : bool{
		$to = $to->add(0, $mob->getSize()->getHeight() / 2, 0);

		foreach (VoxelRayTrace::betweenPoints($from, $to) as $pos) {
			$block = $mob->getWorld()->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);

			if ($block instanceof Liquid && !$detectLiquids) {
				continue;
			}

			if ($block->calculateIntercept($from, $to) !== null) {
				return false;
			}
		}

		return true;
	}
}
