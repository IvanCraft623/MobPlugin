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

use pocketmine\player\Player;

class FreezeWhenLookedAt extends Goal {

	protected Player $target;

	public function __construct(
		protected Enderman $entity
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		$target = $this->entity->getTargetEntity();
		if (!$target instanceof Player) {
			return false;
		}

		if ($this->entity->getLocation()->distanceSquared($target->getLocation()) > 16 ** 2) {
			return false;
		}

		if ($this->entity->isLookingAtMe($target)) {
			$this->target = $target;
			return true;
		}

		return false;
	}

	public function start() : void{
		$this->entity->getNavigation()->stop();
	}

	public function tick() : void{
		$this->entity->getLookControl()->setLookAt($this->target);
	}
}
