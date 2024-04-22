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

namespace IvanCraft623\MobPlugin\entity\golem;

use function array_reverse;

enum IronGolemCrackiness{

	case NONE;
	case LOW;
	case MEDIUM;
	case HIGH;

	public static function fromHealthPercentage(float $percentage) : IronGolemCrackiness{
		foreach (array_reverse(IronGolemCrackiness::cases()) as $crackiness) {
			if ($percentage <= $crackiness->getHeathPercentage()) {
				return $crackiness;
			}
		}

		return IronGolemCrackiness::NONE;
	}

	/**
	 * Returns the minimum percentage of life for this state.
	 *
	 * @return float 0.0-1.0
	 */
	public function getHeathPercentage() : float{
		return match($this){
			self::NONE => 1,
			self::LOW => 0.75,
			self::MEDIUM => 0.5,
			self::HIGH => 0.25
		};
	}
}
