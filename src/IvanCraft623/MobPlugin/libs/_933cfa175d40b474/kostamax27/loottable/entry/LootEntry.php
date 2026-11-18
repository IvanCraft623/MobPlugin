<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_933cfa175d40b474\kostamax27\loottable\entry;

use IvanCraft623\MobPlugin\libs\_933cfa175d40b474\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootEntry{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}