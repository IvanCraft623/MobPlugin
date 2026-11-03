<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_d231130ad4236385\kostamax27\loottable\function;

use Closure;
use IvanCraft623\MobPlugin\libs\_d231130ad4236385\kostamax27\loottable\LootContext;
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