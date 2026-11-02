<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_bad3ae3792f83ce1\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_bad3ae3792f83ce1\kostamax27\loottable\LootContext;
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