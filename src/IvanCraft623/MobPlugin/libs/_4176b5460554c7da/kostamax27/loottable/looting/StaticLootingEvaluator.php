<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\looting;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;

final class StaticLootingEvaluator implements LootingEvaluator{

	/**
	 * @param int<0, max> $level
	 */
	public function __construct(
		readonly public int $level
	){
		$this->level >= 0 || throw new InvalidArgumentException("Looting level must be >= 0, got {$this->level}");
	}

	public function evaluate(LootContext $context) : int{
		return $this->level;
	}
}