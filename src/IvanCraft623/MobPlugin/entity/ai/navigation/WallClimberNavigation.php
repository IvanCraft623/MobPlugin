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

use IvanCraft623\Pathfinder\Path;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\promise\Promise;

class WallClimberNavigation extends GroundPathNavigation{

	private ?Vector3 $pathToPosition = null;

	/**
	 * @phpstan-return Promise<Path>
	 */
	public function createPathToPosition(Vector3 $position, int $reach, ?float $maxDistanceFromStart = null) : Promise{
		$this->pathToPosition = $position->floor();
		$this->speedModifier = 1;

		return parent::createPathToPosition($position, $reach, $maxDistanceFromStart);
	}

	public function moveToEntity(Entity $target, float $speedModifier) : void{
		$this->createPathToEntity($target, 0)->onCompletion(function(Path $path) use ($speedModifier) : void {
			$this->moveToPath($path, $speedModifier);
		}, function(){});

		$this->pathToPosition = $target->getPosition()->floor();
		$this->speedModifier = $speedModifier;
	}

	public function tick() : void{
		if (!$this->isDone()) {
			parent::tick();
			return;
		}

		if ($this->pathToPosition === null) {
			return;
		}

		$mobPosition = $this->mob->getLocation();
		$mobWidthSqr = $this->mob->getSize()->getWidth() ** 2;
		$targetPosition = $this->pathToPosition->add(0.5, 0.5, 0.5);
		if (!($targetPosition->distanceSquared($mobPosition) < $mobWidthSqr) && (
				!($mobPosition->y > $this->pathToPosition->y) ||
				!($targetPosition->withComponents(null, $mobPosition->y, null)->distanceSquared($mobPosition) < $mobWidthSqr)
			)
		) {
			$this->mob->getMoveControl()->setWantedPosition($this->pathToPosition, $this->speedModifier);
		} else {
			$this->pathToPosition = null;
		}
	}
}
