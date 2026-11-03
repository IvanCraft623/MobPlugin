<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\kostamax27\loottable\pool;

use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

interface LootPool{

	/**
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array;
}