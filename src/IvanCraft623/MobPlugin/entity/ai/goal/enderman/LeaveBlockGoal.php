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

namespace IvanCraft623\MobPlugin\entity\ai\goal\enderman;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\monster\Enderman;

use pocketmine\math\Vector3;
use function floor;

class LeaveBlockGoal extends Goal {

	public function __construct(
		protected Enderman $entity
	) { }

	public function canUse() : bool{
		if ($this->entity->getCarriedBlock() === null) {
			return false;
		}

		if (!$this->entity->getSettings()->isMobGriefingEnabled()) {
			return false;
		}

		return $this->entity->getRandom()->nextBoundedInt($this->reducedTickDelay(2000)) === 0;
	}

	public function start() : void{
		$this->entity->getNavigation()->stop();
	}

	public function tick() : void{
		$carriedBlock = $this->entity->getCarriedBlock();
		if ($carriedBlock === null) {
			return;
		}

		$world = $this->entity->getWorld();
		$entityPos = $this->entity->getPosition();

		$random = $this->entity->getRandom();
		$pos = new Vector3(
			floor($entityPos->getX() - 1 + $random->nextFloat() * 2),
			floor($entityPos->getY() + $random->nextFloat() * 2),
			floor($entityPos->getZ() - 1 + $random->nextFloat() * 2)
		);

		if ($this->entity->placeBlock($pos)) {
			$this->entity->setCarriedBlock(null);
		}
	}
}
