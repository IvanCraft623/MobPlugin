<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_34d31b3e433f605c\kostamax27\loottable\looting;

use IvanCraft623\MobPlugin\libs\_34d31b3e433f605c\kostamax27\loottable\LootContext;

interface LootingEvaluator{

	/**
	 * Returns the looting level in effect for a roll.
	 *
	 * @return int<0, max>
	 */
	public function evaluate(LootContext $context) : int;
}