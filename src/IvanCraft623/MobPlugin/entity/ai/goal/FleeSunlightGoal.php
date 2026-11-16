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

use IvanCraft623\MobPlugin\utils\Utils;

class FleeSunlightGoal extends FindCoverGoal {

	public function canUse() : bool{
		if ($this->entity->getTargetEntityId() !== null) {
			return false;
		}

		if (!Utils::isDaytime($this->entity->getWorld())) {
			return false;
		}

		if (!$this->entity->getArmorInventory()->getHelmet()->isNull()) {
			return false;
		}

		return parent::canUse();
	}

	protected function isExposed() : bool{
		return Utils::isSkyVisible($this->entity->getWorld(), $this->entity->getEyePos());
	}
}