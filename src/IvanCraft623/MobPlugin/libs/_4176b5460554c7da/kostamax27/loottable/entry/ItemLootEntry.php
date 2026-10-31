<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry;

use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function\LootFunction;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class ItemLootEntry implements LootEntry{

	/**
	 * @param list<LootFunction> $functions
	 */
	public function __construct(
		readonly public Item $item,
		readonly public array $functions
	){}

	public function generate(LootContext $context) : array{
		$item = clone $this->item;
		foreach($this->functions as $function){
			$item = $function->apply($item, $context);
		}
		if($item->isNull()){
			return [];
		}

		$result = [];
		$max = $item->getMaxStackSize();
		while($item->getCount() > $max){
			$result[] = $item->pop($max);
		}
		$result[] = $item;
		return $result;
	}
}