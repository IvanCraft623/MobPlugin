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

namespace IvanCraft623\MobPlugin\entity\ai\sensing;

use IvanCraft623\MobPlugin\entity\ai\memory\MemoryModuleType;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\Living;
use IvanCraft623\MobPlugin\MobPlugin;

use pocketmine\entity\Living as PMLiving;

abstract class Sensor {

	private int $scanRate;

	private int $timeToTick;

	public function __construct(int $rate = 20) {
		$this->scanRate = $rate;
		$this->timeToTick = MobPlugin::getInstance()->getRandom()->nextBoundedInt($rate);
	}

	public function tick(Living $entity) : void {
		$timeToTick = $this->timeToTick - 1;
		$this->timeToTick = $timeToTick;
		if ($timeToTick <= 0) {
			$this->timeToTick = $this->scanRate;
			$this->doTick($entity);
		}
	}

	abstract protected function doTick(Living $entity) : void;

	abstract protected function requires() : array;

	protected function isEntityTargetable(Living $entity, PMLiving $target) : bool {
		if ($entity->getBrain()->isMemoryValue(MemoryModuleType::ATTACK_TARGET(), $target)) {
			return self::TARGET_CONDITIONS_IGNORE_INVISIBILITY_TESTING()->test($entity, $target);
		}
		return self::TARGET_CONDITIONS()->test($entity, $target);
	}

	public static function TARGET_CONDITIONS_IGNORE_INVISIBILITY_TESTING() : TargetingConditions {
		return new TargetingConditions(16, false, false, true, true);
	}

	public static function TARGET_CONDITIONS() : TargetingConditions {
		return new TargetingConditions(16, false, false, true, false);
	}
}
