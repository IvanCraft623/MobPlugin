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
use IvanCraft623\MobPlugin\entity\ai\control\SwimThrust;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\TraversalRules;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\math\Vector3;
use pocketmine\world\World;

abstract class AquaticLocomotion extends Locomotion{

	public function __construct(
		private int $maxPitchChange,
		private int $maxYawChange,
		private float $landSpeed,
		private bool $buoyant,
		private SwimThrust $thrust
	) {
	}

	public function createMoveControl(PathfinderMob $mob, PathNavigation $navigation) : MoveControl{
		return new AquaticMoveControl($mob, $navigation, $this->maxPitchChange, $this->maxYawChange, $this->landSpeed, $this->buoyant, $this->thrust);
	}

	public function swimsAt(World $world, TraversalRules $rules, Vector3 $position) : bool{
		return true;
	}

	public function canFollowPath(PathfinderMob $mob, TraversalRules $rules) : bool{
		return true;
	}

	public function pathPosition(PathfinderMob $mob, TraversalRules $rules) : Vector3{
		$position = $mob->getPosition();
		return new Vector3($position->x, $position->y + $mob->getSize()->getHeight() / 2, $position->z);
	}

	public function canMoveDirectly(PathfinderMob $mob, Vector3 $from, Vector3 $to) : bool{
		return self::isClearForMovementBetween($mob, $from, $to, false);
	}
}
