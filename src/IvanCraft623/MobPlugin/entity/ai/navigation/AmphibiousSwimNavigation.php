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

namespace IvanCraft623\MobPlugin\entity\ai\navigation;

use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\IvanCraft623\Pathfinder\evaluator\AmphibiousNodeEvaluator;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Vector3;

class AmphibiousSwimNavigation extends PathNavigation {

	protected function createPathFinder() : void{
		$this->nodeEvaluator = new AmphibiousNodeEvaluator($this->mob->getPathTypeCostMap());
		$this->nodeEvaluator->setCanPassDoors();
	}

	public function canUpdatePath() : bool{
		return true;
	}

	public function getTempMobPosition() : Vector3{
		$position = $this->mob->getPosition();
		return new Vector3($position->x, $position->y + $this->mob->getSize()->getHeight() / 2, $position->z);
	}

	protected function getGroundY(Vector3 $position) : float{
		return $position->y;
	}

	protected function canMoveDirectly(Vector3 $from, Vector3 $to) : bool{
		return static::isClearForMovementBetween($this->mob, $from, $to, false);
	}

	public function isStableDestination(Vector3 $position) : bool{
		return $this->getWorld()->getBlockAt($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ())->getTypeId() !== BlockTypeIds::AIR;
	}
}