<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\condition;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\looting\LootingEvaluator;

final class RandomChanceWithLootingLootCondition implements LootCondition{

	public function __construct(
		readonly public LootingEvaluator $looting,
		readonly public float $chance,
		readonly public float $looting_multiplier
	){
		$this->chance >= 0.0 && $this->chance <= 1.0 || throw new InvalidArgumentException("'chance' must be within [0, 1], got {$this->chance}");
	}

	public function test(LootContext $context) : bool{
		return $context->random->nextFloat() < $this->chance + $this->looting->evaluate($context) * $this->looting_multiplier;
	}
}