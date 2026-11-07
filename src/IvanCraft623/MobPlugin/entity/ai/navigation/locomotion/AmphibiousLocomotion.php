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

use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\evaluator\AmphibiousNodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Vector3;

class AmphibiousLocomotion extends AquaticLocomotion{
	public function __construct(
		int $maxPitchChange = 85,
		int $maxYawChange = 10,
		float $landSpeed = 1.0,
		bool $buoyant = false,
		bool $gentleVertical = true
	) {
		parent::__construct($maxPitchChange, $maxYawChange, $landSpeed, $buoyant, $gentleVertical);
	}

	public function createEvaluator() : NodeEvaluator{
		return new AmphibiousNodeEvaluator();
	}

	public function searchCostMap(PathfinderMob $mob) : BlockPathTypeCostMap{
		//searches only, wander targets keep reading the mob's own map
		return BlockPathTypeCostMap::amphibious()->merge($mob->getNavigation()->getCostMap());
	}

	public function isStableDestination(PathfinderMob $mob, Vector3 $position) : bool{
		return $mob->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->getTypeId() !== BlockTypeIds::AIR;
	}
}
