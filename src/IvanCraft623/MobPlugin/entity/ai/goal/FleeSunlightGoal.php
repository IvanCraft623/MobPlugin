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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class FleeSunlightGoal extends Goal {

	protected Vector3 $hidePosition;

	public function __construct(protected PathfinderMob $entity, protected float $speedModifier) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		if ($this->entity->getTargetEntityId() !== null) {
			return false;
		}

		$world = $this->entity->getWorld();
		$time = $world->getTimeOfDay();
		if ($time >= World::TIME_NIGHT && $time < World::TIME_SUNRISE) {
			return false;
		}

		if (!Utils::isSkyVisible($world, $this->entity->getEyePos())) {
			return false;
		}

		if (!$this->entity->getArmorInventory()->getHelmet()->isNull()) {
			return false;
		}

		$pos = $this->getHidePosition();
		if ($pos === null) {
			return false;
		}

		$this->hidePosition = $pos;
		return true;
	}

	public function canContinueToUse() : bool{
		return !$this->entity->getNavigation()->isDone();
	}

	public function start() : void{
		$this->entity->getNavigation()->moveToXYZ($this->hidePosition->x, $this->hidePosition->y, $this->hidePosition->z, $this->speedModifier);
	}

	public function stop() : void{
		parent::stop();

		unset($this->hidePosition);
	}

	protected function getHidePosition() : ?Vector3{
		$random = $this->entity->getRandom();
		$entityPosition = $this->entity->getPosition();
		$world = $this->entity->getWorld();

		for ($i = 0; $i < 10; ++$i) {
			$p = $entityPosition->add(
				$random->nextBoundedInt(20) - 10,
				$random->nextBoundedInt(6) - 3,
				$random->nextBoundedInt(20) - 10
			);
			if (!Utils::isSkyVisible($world, $p) && $this->entity->getWalkTargetValue($p) < 0) {
				return $p->floor()->add(0.5, 0, 0.5);
			}
		}

		return null;
	}
}
