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

use IvanCraft623\MobPlugin\entity\ai\control\GroundMoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\TraversalRules;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathCursor;
use IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\block\FillableCauldron;
use pocketmine\block\Liquid;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use function count;
use function floor;

/**
 * A way of moving: walking, flying, swimming. The hooks default to a walker's.
 */
abstract class Locomotion{
	abstract public function createEvaluator() : NodeEvaluator;

	/**
	 * The control that steers a mob along paths of this kind.
	 */
	public function createMoveControl(PathfinderMob $mob, PathNavigation $navigation) : MoveControl{
		return new GroundMoveControl($mob, $navigation);
	}

	abstract public function canFollowPath(PathfinderMob $mob, TraversalRules $rules) : bool;

	/**
	 * Where the mob is for following a path.
	 */
	abstract public function pathPosition(PathfinderMob $mob, TraversalRules $rules) : Vector3;

	abstract public function isStableDestination(PathfinderMob $mob, Vector3 $position) : bool;

	/**
	 * Costs for searches; wander targets read the mob's own map.
	 */
	public function searchCostMap(PathfinderMob $mob) : BlockPathTypeCostMap{
		return $mob->getNavigation()->getCostMap();
	}

	final public function createProfile(PathfinderMob $mob, TraversalRules $rules) : MovementProfile{
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
	public function wantedY(PathfinderMob $mob, BlockGetter $blockGetter, Vector3 $position) : float{
		return $position->y;
	}

	/**
	 * The target to search to, e.g. snapped to the ground.
	 */
	public function adjustTarget(PathfinderMob $mob, Vector3 $target) : Vector3{
		return $target;
	}

	/**
	 * The computed path fitted to the mob: the same one when nothing needs to change.
	 */
	public function adjustPath(PathfinderMob $mob, Path $path, TraversalRules $rules) : Path{
		$world = $mob->getWorld();
		$nodes = $path->getNodes();
		$count = count($nodes);
		$changed = false;
		for ($i = 0; $i < $count; $i++) {
			$node = $nodes[$i];
			if ($world->getBlockAt($node->x(), $node->y(), $node->z()) instanceof FillableCauldron) {
				$nodes[$i] = $node->cloneAndMove($node->x(), $node->y() + 1, $node->z());

				$nextNode = $i + 1 < $count ? $nodes[$i + 1] : null;
				if ($nextNode !== null && $node->y >= $nextNode->y) {
					$nodes[$i + 1] = $node->cloneAndMove($nextNode->x(), $node->y() + 1, $nextNode->z());
				}
				$changed = true;
			}
		}

		return $changed ? $path->withNodes($nodes) : $path;
	}

	/**
	 * Whether the mob can skip the nodes between two points and go straight.
	 */
	public function canMoveDirectly(PathfinderMob $mob, Vector3 $from, Vector3 $to) : bool{
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
	public function advanceWithoutFollowing(PathfinderMob $mob, PathCursor $cursor, Vector3 $position) : void{
		$nextPosition = $cursor->getNextNodeCenter();
		if ($position->y > $nextPosition->y &&
			!$mob->isOnGround() &&
			floor($position->x) === floor($nextPosition->x) &&
			floor($position->z) === floor($nextPosition->z)
		) {
			$cursor->advance();
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
	public function tickWithoutPath(PathfinderMob $mob) : void{
	}

	public static function isInLiquid(Mob $mob) : bool{
		//Water and lava don't have collision boxes, so they never show up in getCollisionBlocks().
		return $mob->isInWater() || $mob->isInLava();
	}

	protected static function isClearForMovementBetween(PathfinderMob $mob, Vector3 $from, Vector3 $to, bool $detectLiquids) : bool{
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
