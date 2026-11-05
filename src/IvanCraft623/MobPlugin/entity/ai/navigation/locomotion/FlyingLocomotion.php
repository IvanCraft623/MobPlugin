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

use IvanCraft623\MobPlugin\entity\ai\control\FlightMoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\TraversalRules;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\Pathfinder\evaluator\FlightNodeEvaluator;
use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\PathCursor;
use pocketmine\block\utils\SupportType;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;

class FlyingLocomotion extends Locomotion{
	public function __construct(
		private int $maxPitchChange = 10,
		private bool $noGravity = false
	) {
	}

	public function createMoveControl(PathfinderMob $mob, PathNavigation $navigation) : MoveControl{
		return new FlightMoveControl($mob, $navigation, $this->maxPitchChange, $this->noGravity);
	}

	public function createEvaluator() : NodeEvaluator{
		return new FlightNodeEvaluator();
	}

	public function canFollowPath(PathfinderMob $mob, TraversalRules $rules) : bool{
		return $rules->canFloat || self::isInLiquid($mob);
	}

	public function pathPosition(PathfinderMob $mob, TraversalRules $rules) : Vector3{
		return $mob->getPosition();
	}

	public function isStableDestination(PathfinderMob $mob, Vector3 $position) : bool{
		return $mob->getWorld()->getBlock($position)->getSupportType(Facing::UP) === SupportType::FULL;
	}

	public function canMoveDirectly(PathfinderMob $mob, Vector3 $from, Vector3 $to) : bool{
		return self::isClearForMovementBetween($mob, $from, $to, true);
	}

	public function advanceWithoutFollowing(PathfinderMob $mob, PathCursor $cursor, Vector3 $position) : void{
		if ($position->floor()->equals($cursor->getNextNodeCenter()->floor())) {
			$cursor->advance();
		}
	}
}
