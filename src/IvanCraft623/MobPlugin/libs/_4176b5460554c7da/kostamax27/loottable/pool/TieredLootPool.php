<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\pool;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\condition\LootCondition;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\LootEntry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;

final class TieredLootPool implements LootPool{

	/**
	 * @param list<LootEntry> $entries ordered from lowest to highest tier
	 * @param positive-int $initial_range
	 * @param int<0, max> $bonus_rolls
	 * @param float $bonus_chance within [0, 1]
	 * @param list<LootCondition> $conditions
	 */
	public function __construct(
		readonly public array $entries,
		readonly public int $initial_range = 1,
		readonly public int $bonus_rolls = 0,
		readonly public float $bonus_chance = 0.0,
		readonly public array $conditions = []
	){
		$this->initial_range >= 1 || throw new InvalidArgumentException("'initial_range' must be >= 1, got {$this->initial_range}");
		$this->bonus_rolls >= 0 || throw new InvalidArgumentException("'bonus_rolls' must be >= 0, got {$this->bonus_rolls}");
		$this->bonus_chance >= 0.0 && $this->bonus_chance <= 1.0 || throw new InvalidArgumentException("'bonus_chance' must be within [0, 1], got {$this->bonus_chance}");
	}

	public function generate(LootContext $context) : array{
		foreach($this->conditions as $condition){
			if(!$condition->test($context)){
				return [];
			}
		}
		$index = $this->rollTier($context) - 1;
		return isset($this->entries[$index]) ? $this->entries[$index]->generate($context) : [];
	}

	/**
	 * @return positive-int one-indexed tier
	 */
	public function rollTier(LootContext $context) : int{
		$index = $this->initial_range === 1 ? 1 : $context->random->nextRange(1, $this->initial_range);
		for($i = 0; $i < $this->bonus_rolls; ++$i){
			if($context->random->nextFloat() < $this->bonus_chance){
				++$index;
			}
		}
		return $index;
	}
}