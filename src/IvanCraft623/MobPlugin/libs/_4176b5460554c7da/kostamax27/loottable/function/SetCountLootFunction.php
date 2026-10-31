<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\item\Item;
use function max;

final class SetCountLootFunction implements LootFunction{

	public function __construct(
		readonly public IntRange $count,
		readonly public bool $add = false
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$count = $this->count->sample($context->random);
		$item->setCount(max(0, $this->add ? $item->getCount() + $count : $count));
		return $item;
	}
}