<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_9082f0cf01fb9e8d\kostamax27\loottable\entry;

use IvanCraft623\MobPlugin\libs\_9082f0cf01fb9e8d\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootEntry{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}