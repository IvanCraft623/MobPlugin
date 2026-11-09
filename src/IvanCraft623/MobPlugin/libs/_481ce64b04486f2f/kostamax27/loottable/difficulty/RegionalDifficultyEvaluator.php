<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\kostamax27\loottable\difficulty;

use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\kostamax27\loottable\LootContext;

interface RegionalDifficultyEvaluator{

	/**
	 * Returns the regional difficulty in effect for a roll.
	 *
	 * @return float 0.0-1.0
	 */
	public function evaluate(LootContext $context) : float;
}