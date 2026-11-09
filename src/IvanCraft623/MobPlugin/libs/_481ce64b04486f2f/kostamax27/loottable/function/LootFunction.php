<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootFunction{

	/**
	 * Applies the function to $item. The returned item may be the same
	 * instance mutated, or a different one (e.g. furnace_smelt).
	 */
	public function apply(Item $item, LootContext $context) : Item;
}