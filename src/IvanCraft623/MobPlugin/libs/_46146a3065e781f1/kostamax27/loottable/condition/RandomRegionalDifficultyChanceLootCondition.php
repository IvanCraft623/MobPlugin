<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\difficulty\RegionalDifficultyEvaluator;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\LootContext;

final class RandomRegionalDifficultyChanceLootCondition implements LootCondition{

	public function __construct(
		readonly public RegionalDifficultyEvaluator $evaluator,
		readonly public float $max_chance
	){}

	public function test(LootContext $context) : bool{
		return $this->evaluator->evaluate($context) * $this->max_chance > $context->random->nextFloat();
	}
}