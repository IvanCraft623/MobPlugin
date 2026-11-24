<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f8fbc2a3e2ee221f\kostamax27\loottable\entry;

use IvanCraft623\MobPlugin\libs\_f8fbc2a3e2ee221f\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootEntry{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}