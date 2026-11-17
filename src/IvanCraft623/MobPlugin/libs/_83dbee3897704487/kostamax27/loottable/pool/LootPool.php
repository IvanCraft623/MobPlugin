<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_83dbee3897704487\kostamax27\loottable\pool;

use IvanCraft623\MobPlugin\libs\_83dbee3897704487\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootPool{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}