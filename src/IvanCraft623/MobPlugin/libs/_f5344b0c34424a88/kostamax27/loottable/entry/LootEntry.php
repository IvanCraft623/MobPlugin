<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\entry;

use IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootEntry{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}