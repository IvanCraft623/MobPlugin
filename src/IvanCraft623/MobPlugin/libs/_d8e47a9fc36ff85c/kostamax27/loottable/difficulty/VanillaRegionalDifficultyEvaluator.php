<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\kostamax27\loottable\difficulty;

use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\kostamax27\loottable\LootContext;
use IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\kostamax27\loottable\LootDifficulty;
use pocketmine\world\World;
use function abs;
use function intdiv;

/**
 * Bedrock's value, which is not regional: 0 on peaceful and easy, 1 on hard, and on normal
 * 0.375-0.625 depending on the moon of the entity's world (a new moon without a world).
 */
final class VanillaRegionalDifficultyEvaluator implements RegionalDifficultyEvaluator{

	private const NORMAL_BASE = 0.375;
	private const MOON_FACTOR = 0.25;
	private const MOON_PHASES = 8;
	private const NEW_MOON_PHASE = 4;

	public function evaluate(LootContext $context) : float{
		return match($context->difficulty){
			LootDifficulty::HARD => 1.0,
			LootDifficulty::NORMAL => self::NORMAL_BASE + self::MOON_FACTOR * self::moonBrightness($context->entity?->getWorld()),
			default => 0.0
		};
	}

	private static function moonBrightness(?World $world) : float{
		if($world === null){
			return 0.0;
		}
		return abs((intdiv($world->getTime(), World::TIME_FULL) & (self::MOON_PHASES - 1)) - self::NEW_MOON_PHASE) / self::NEW_MOON_PHASE;
	}
}