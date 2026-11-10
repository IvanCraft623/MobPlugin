<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\entry;

use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootEntry{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}