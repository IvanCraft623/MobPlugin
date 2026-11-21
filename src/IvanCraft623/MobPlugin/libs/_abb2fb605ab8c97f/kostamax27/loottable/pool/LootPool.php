<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_abb2fb605ab8c97f\kostamax27\loottable\pool;

use IvanCraft623\MobPlugin\libs\_abb2fb605ab8c97f\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootPool{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}