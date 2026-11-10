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

use IvanCraft623\MobPlugin\entity\ai\control\SwimThrust;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\evaluator\SwimNodeEvaluator;
use pocketmine\block\Water;
use pocketmine\math\Vector3;

/**
 * For mobs that never leave the water.
 */
class SwimLocomotion extends AquaticLocomotion{

	public function __construct(int $maxPitchChange = 85, int $maxYawChange = 10, SwimThrust $thrust = SwimThrust::EASED) {
		parent::__construct($maxPitchChange, $maxYawChange, 1.0, false, $thrust);
	}

	public function createEvaluator() : NodeEvaluator{
		return new SwimNodeEvaluator();
	}

	public function searchCostMap(PathfinderMob $mob) : BlockPathTypeCostMap{
		return $mob->getNavigation()->getCostMap();
	}

	public function isStableDestination(PathfinderMob $mob, Vector3 $position) : bool{
		return $mob->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()) instanceof Water;
	}
}