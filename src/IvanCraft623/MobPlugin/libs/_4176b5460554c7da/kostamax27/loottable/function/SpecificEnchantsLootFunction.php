<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\enchant\ItemEnchanter;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\item\Item;
use function array_map;

final class SpecificEnchantsLootFunction implements LootFunction{

	/**
	 * @param ItemEnchanter $enchanter
	 * @param list<SpecificEnchant> $enchants
	 */
	public function __construct(
		readonly public ItemEnchanter $enchanter,
		readonly public array $enchants
	){}

	public function apply(Item $item, LootContext $context) : Item{
		return $this->enchanter->enchant($item, ...array_map(static fn(SpecificEnchant $enchant) => $enchant->roll($context), $this->enchants));
	}
}