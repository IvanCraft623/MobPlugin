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
use IvanCraft623\MobPlugin\entity\ai\navigation\TraversalRules;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\Pathfinder\evaluator\AmphibiousNodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Vector3;
use pocketmine\world\World;

use function floor;

class AmphibiousLocomotion extends AquaticLocomotion{
	public function __construct(
		int $maxPitchChange = 85,
		int $maxYawChange = 10,
		float $landSpeed = 1.0,
		bool $buoyant = false,
		SwimThrust $thrust = SwimThrust::EASED,
		private bool $walksSeabed = false
	) {
		parent::__construct($maxPitchChange, $maxYawChange, $landSpeed, $buoyant, $thrust);
	}

	/**
	 * Swims when the rules allow it. With $walksSeabed, it walks where the water has a solid block below.
	 */
	public function swimsAt(World $world, TraversalRules $rules, Vector3 $position) : bool{
		return $rules->canSwim && (
			!$this->walksSeabed ||
			!$world->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->isSolid()
		);
	}

	/**
	 * A mob that wades can't cut across a climb, it needs to reach the step.
	 */
	public function canMoveDirectly(PathfinderMob $mob, Vector3 $from, Vector3 $to) : bool{
		if (floor($to->y) !== floor($mob->getPosition()->y) && !$this->swimsAt($mob->getWorld(), $mob->getNavigation()->getRules(), $to)) {
			return false;
		}

		return parent::canMoveDirectly($mob, $from, $to);
	}

	public function createEvaluator() : NodeEvaluator{
		return new AmphibiousNodeEvaluator();
	}

	public function searchCostMap(PathfinderMob $mob) : BlockPathTypeCostMap{
		//searches only, wander targets keep reading the mob's own map
		return BlockPathTypeCostMap::amphibious()->merge($mob->getNavigation()->getCostMap());
	}

	public function isGroundBased() : bool{
		return true;
	}

	public function isStableDestination(PathfinderMob $mob, Vector3 $position) : bool{
		return $mob->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->getTypeId() !== BlockTypeIds::AIR;
	}
}
