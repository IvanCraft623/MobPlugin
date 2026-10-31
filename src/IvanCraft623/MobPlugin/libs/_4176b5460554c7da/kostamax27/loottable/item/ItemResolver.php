<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\item;

use pocketmine\item\Item;

interface ItemResolver{

	/**
	 * Translates an item name from a loot table into an item, or returns
	 * null if the name is unknown to this resolver.
	 *
	 * @param string $name item name as written in the loot table, e.g. "minecraft:iron_ingot", "minecraft:muttonRaw", "wool"
	 * @param int $meta legacy data value from set_data / random_aux_value, 0 when absent
	 */
	public function resolve(string $name, int $meta = 0) : ?Item;
}