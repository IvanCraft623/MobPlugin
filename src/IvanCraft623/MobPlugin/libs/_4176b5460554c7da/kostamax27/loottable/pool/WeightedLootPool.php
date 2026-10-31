<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\pool;

use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\condition\LootCondition;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\WeightedLootEntry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\FloatRange;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use function array_push;
use function count;
use function floor;

final class WeightedLootPool implements LootPool{

	/**
	 * @param list<WeightedLootEntry> $entries
	 * @param FloatRange $bonus_rolls extra rolls per point of luck
	 * @param list<LootCondition> $conditions
	 */
	public function __construct(
		readonly public array $entries,
		readonly public IntRange $rolls = new IntRange(1, 1),
		readonly public FloatRange $bonus_rolls = new FloatRange(0.0, 0.0),
		readonly public array $conditions = []
	){}

	public function generate(LootContext $context) : array{
		foreach($this->conditions as $condition){
			if(!$condition->test($context)){
				return [];
			}
		}
		$rolls = $this->rolls->sample($context->random) + (int) floor($this->bonus_rolls->sample($context->random) * $context->luck);
		$result = [];
		for($i = 0; $i < $rolls; ++$i){
			$entry = $this->pick($context);
			if($entry !== null){
				array_push($result, ...$entry->entry->generate($context));
			}
		}
		return $result;
	}

	private function pick(LootContext $context) : ?WeightedLootEntry{
		$candidates = [];
		$total = 0;
		foreach($this->entries as $entry){
			if(!$entry->test($context)){
				continue;
			}
			$weight = $entry->effectiveWeight($context);
			if($weight <= 0){
				continue;
			}
			$candidates[] = [$entry, $weight];
			$total += $weight;
		}
		if(count($candidates) === 0){
			return null;
		}
		$pick = $context->random->nextBoundedInt($total);
		foreach($candidates as [$entry, $weight]){
			if(($pick -= $weight) < 0){
				return $entry;
			}
		}
		return null;
	}
}