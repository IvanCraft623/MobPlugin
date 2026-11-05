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

use IvanCraft623\MobPlugin\entity\ai\control\AquaticMoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\TraversalRules;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\evaluator\AmphibiousNodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Vector3;

class AmphibiousLocomotion extends Locomotion{
	public function __construct(
		private int $maxPitchChange = 85,
		private int $maxYawChange = 10,
		private float $waterSpeed = 1.0,
		private float $landSpeed = 1.0,
		private bool $buoyant = false
	) {
	}

	public function createMoveControl(PathfinderMob $mob, PathNavigation $navigation) : MoveControl{
		return new AquaticMoveControl($mob, $navigation, $this->maxPitchChange, $this->maxYawChange, $this->waterSpeed, $this->landSpeed, $this->buoyant);
	}

	public function createEvaluator() : NodeEvaluator{
		return new AmphibiousNodeEvaluator();
	}

	public function searchCostMap(PathfinderMob $mob) : BlockPathTypeCostMap{
		//searches only, wander targets keep reading the mob's own map
		return BlockPathTypeCostMap::amphibious()->merge($mob->getNavigation()->getCostMap());
	}

	public function canFollowPath(PathfinderMob $mob, TraversalRules $rules) : bool{
		return true;
	}

	public function pathPosition(PathfinderMob $mob, TraversalRules $rules) : Vector3{
		$position = $mob->getPosition();
		return new Vector3($position->x, $position->y + $mob->getSize()->getHeight() / 2, $position->z);
	}

	public function isStableDestination(PathfinderMob $mob, Vector3 $position) : bool{
		return $mob->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->getTypeId() !== BlockTypeIds::AIR;
	}

	public function canMoveDirectly(PathfinderMob $mob, Vector3 $from, Vector3 $to) : bool{
		return self::isClearForMovementBetween($mob, $from, $to, false);
	}
}
