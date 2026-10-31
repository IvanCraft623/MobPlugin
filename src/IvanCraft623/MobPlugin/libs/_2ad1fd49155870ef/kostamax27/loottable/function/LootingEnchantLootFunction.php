<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\looting\LootingEvaluator;
use pocketmine\item\Item;
use function max;
use function min;

final class LootingEnchantLootFunction implements LootFunction{

	public function __construct(
		readonly public LootingEvaluator $looting,
		readonly public IntRange $count,
		readonly public ?int $limit = null
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$level = $this->looting->evaluate($context);
		if($level <= 0){
			return $item;
		}
		$count = $item->getCount();
		for($i = 0; $i < $level; ++$i){
			$count += $this->count->sample($context->random);
		}
		if($this->limit !== null){
			$count = min($count, $this->limit);
		}
		$item->setCount(max(0, $count));
		return $item;
	}
}