<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\enchant\ItemEnchanter;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use function count;

final class EnchantRandomlyLootFunction implements LootFunction{

	public function __construct(
		readonly public ItemEnchanter $enchanter,
		readonly public bool $treasure = false
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$candidates = $this->enchanter->availableEnchantments($item, $this->treasure);
		if(count($candidates) === 0){
			return $item;
		}
		$enchantment = $candidates[$context->random->nextBoundedInt(count($candidates))];
		$level = $enchantment->getMaxLevel() === 1 ? 1 : $context->random->nextRange(1, $enchantment->getMaxLevel());
		return $this->enchanter->enchant($item, new EnchantmentInstance($enchantment, $level));
	}
}