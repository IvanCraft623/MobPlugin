<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class SetNameLootFunction implements LootFunction{

	public function __construct(
		readonly public string $name
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$item->setCustomName($this->name);
		return $item;
	}
}