<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\kostamax27\loottable\function;

use Closure;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class ClosureLootFunction implements LootFunction{

	/**
	 * @param Closure(Item, LootContext) : Item $closure
	 */
	public function __construct(
		readonly private Closure $closure
	){}

	public function apply(Item $item, LootContext $context) : Item{
		return ($this->closure)($item, $context);
	}
}