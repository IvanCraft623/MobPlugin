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

namespace IvanCraft623\MobPlugin\utils;

use pocketmine\item\Bow;
use pocketmine\item\Releasable;
use function max;
use function min;

class Utils {

	public static function clamp(float $value, float $minValue, float $maxValue) : float {
		return max($minValue, min($maxValue, $value));
	}

	public static function wrapDegrees(float $degrees) : float {
		$result = $degrees % 360;
		if ($result >= 180) {
			$result -= 360;
		}
		if ($result < -180) {
			$result += 360;
		}
		return $result;
	}

	public static function degreesDifference(float $degrees1, float $degrees2) : float {
		return self::wrapDegrees($degrees2 - $degrees1);
	}

	public static function getDefaultProjectileRange(Releasable $item) : int {
		if ($item instanceof Bow) {
			return 15;
		}
		return 8;
	}

	public static function rotateIfNecessary(float $currentDegrees, float $targetDegrees, float $maxDifference) : float {
		return $targetDegrees - self::clamp(self::degreesDifference($currentDegrees, $targetDegrees), -$maxDifference, $maxDifference);
	}
}
