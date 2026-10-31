<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use Closure;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
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