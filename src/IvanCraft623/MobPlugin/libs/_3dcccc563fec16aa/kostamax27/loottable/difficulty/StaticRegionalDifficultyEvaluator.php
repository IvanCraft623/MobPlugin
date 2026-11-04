<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\kostamax27\loottable\difficulty;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\kostamax27\loottable\LootContext;

final class StaticRegionalDifficultyEvaluator implements RegionalDifficultyEvaluator{

	public function __construct(
		readonly public float $difficulty
	){
		if($this->difficulty < 0.0 || $this->difficulty > 1.0){
			throw new InvalidArgumentException("Regional difficulty must be within [0, 1], got {$this->difficulty}");
		}
	}

	public function evaluate(LootContext $context) : float{
		return $this->difficulty;
	}
}