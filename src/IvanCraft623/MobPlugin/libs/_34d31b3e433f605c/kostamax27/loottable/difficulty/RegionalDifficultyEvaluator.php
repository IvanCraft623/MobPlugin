<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_34d31b3e433f605c\kostamax27\loottable\difficulty;

use IvanCraft623\MobPlugin\libs\_34d31b3e433f605c\kostamax27\loottable\LootContext;

interface RegionalDifficultyEvaluator{

	/**
	 * Returns the regional difficulty in effect for a roll.
	 *
	 * @return float 0.0-1.0
	 */
	public function evaluate(LootContext $context) : float;
}