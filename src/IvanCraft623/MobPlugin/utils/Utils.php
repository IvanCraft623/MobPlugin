<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\utils;

use pocketmine\item\Bow;
use pocketmine\item\Releasable;

class Utils {

	public static function clamp(float $float1, float $float2, float $float3): float {
		if ($float1 < $float2) {
			return $float2;
		}
		if ($float1 > $float3) {
			return $float3;
		}
		return $float1;
	}

	public static function wrapDegrees(float $number): float {
        $result = $number % 360;
        if ($result >= 180) {
            $result -= 360;
        }
        if ($result < -180) {
            $result += 360;
        }
        return $result;
    }

    public static function degreesDifference(float $float1, float $float2): float {
        return self::wrapDegrees($float2 - $float1);
    }

	public static function getDefaultProjectileRange(Releasable $item): {
		if ($item instanceof Bow) {
			return 15;
		}
		return 8;
	}

	public static function rotateIfNecessary(float $float1, float $float2, float $float3): float {
		$float4 = self::degreesDifference($float1, $float2);
		$float5 = self::clamp($float4, -$float3, $float3);
		return $float2 - $float5;
	}
}