<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\condition\LootCondition;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use function floor;
use function max;

final class WeightedLootEntry{

	/**
	 * @param int<0, max> $weight
	 * @param int $quality per-luck-point weight bonus, may be negative
	 * @param list<LootCondition> $conditions
	 */
	public function __construct(
		readonly public LootEntry $entry,
		readonly public int $weight = 1,
		readonly public int $quality = 0,
		readonly public array $conditions = []
	){
		$this->weight >= 0 || throw new InvalidArgumentException("'weight' must be >= 0, got {$this->weight}");
	}

	/**
	 * @return int<0, max>
	 */
	public function effectiveWeight(LootContext $context) : int{
		return max(0, $this->weight + (int) floor($this->quality * $context->luck));
	}

	public function test(LootContext $context) : bool{
		foreach($this->conditions as $condition){
			if(!$condition->test($context)){
				return false;
			}
		}
		return true;
	}
}