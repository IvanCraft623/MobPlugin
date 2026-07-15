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

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\Pathfinder\evaluator\FlightNodeEvaluator;
use IvanCraft623\Pathfinder\Path;

use pocketmine\block\utils\SupportType;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;

class FlyingPathNavigation extends PathNavigation{

	protected function createPathFinder() : void{
		$this->nodeEvaluator = new FlightNodeEvaluator(); //it ignores mob's path type map?
		$this->nodeEvaluator->setCanPassDoors();
	}

	protected function canMoveDirectly(Vector3 $from, Vector3 $to) : bool{
		return static::isClearForMovementBetween($this->mob, $from, $to, true);
	}

	public function canUpdatePath() : bool{
		return $this->canFloat() || $this->isInLiquid()/* || $this->mob->isPassenger()*/;
	}

	public function getTempMobPosition() : Vector3 {
		return $this->mob->getPosition();
	}

	public function tick() : void{
		$this->tick++;
		if ($this->hasDelayedRecomputation) {
			$this->recomputePath();
		}

		if (!$this->isDone()) {
			if ($this->canUpdatePath()) {
				$this->followThePath();
			} elseif ($this->path !== null && !$this->path->isDone()) {
				$nextPos = $this->path->getNextEntityPosition($this->mob);
				if ($this->getTempMobPosition()->floor()->equals($nextPos->floor())) {
					$this->path->advance();
				}
			}

			if ($this->path !== null && !$this->isDone()) {
				$this->mob->getMoveControl()->setWantedPosition(
					$this->path->getNextEntityPosition($this->mob),
					$this->speedModifier
				);
			}
		}
	}

	public function setCanOpenDoors(bool $value = true) : void{
		$this->nodeEvaluator->setCanOpenDoors($value);
	}

	public function canOpenDoors() : bool{
		return $this->nodeEvaluator->canOpenDoors();
	}

	public function setCanPassDoors(bool $value = true) : void{
		$this->nodeEvaluator->setCanPassDoors($value);
	}

	public function canPassDoors() : bool{
		return $this->nodeEvaluator->canPassDoors();
	}

	public function isStableDestination(Vector3 $position) : bool{
		//uhh, maybe theres a better way to do this
		return $this->getWorld()->getBlock($position)->getSupportType(Facing::UP) === SupportType::FULL;
	}
}
