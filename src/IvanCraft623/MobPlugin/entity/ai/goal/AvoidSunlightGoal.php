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

use IvanCraft623\MobPlugin\entity\ai\navigation\GroundPathNavigation;
use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\world\World;

class AvoidSunlightGoal extends Goal {

	public function __construct(protected Mob $entity) { }

	public function canUse() : bool{
		$time = $this->entity->getWorld()->getTimeOfDay();
			return !($time >= World::TIME_NIGHT && $time < World::TIME_SUNRISE) && //is day check
				$this->entity->getArmorInventory()->getHelmet()->isNull() &&
				$this->entity->getNavigation() instanceof GroundPathNavigation;
	}

	public function start() : void{
		if (($navigation = $this->entity->getNavigation()) instanceof GroundPathNavigation) {
			$navigation->setAvoidSun(true);
		}
	}

	public function stop() : void{
		if (($navigation = $this->entity->getNavigation()) instanceof GroundPathNavigation) {
			$navigation->setAvoidSun(false);
		}
	}
}
