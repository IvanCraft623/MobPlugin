<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class SetLoreLootFunction implements LootFunction{

	/**
	 * @param list<string> $lore
	 */
	public function __construct(
		readonly public array $lore
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$item->setLore($this->lore);
		return $item;
	}
}