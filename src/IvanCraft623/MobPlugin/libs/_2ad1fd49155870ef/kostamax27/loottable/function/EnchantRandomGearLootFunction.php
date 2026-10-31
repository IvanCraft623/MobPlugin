<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class EnchantRandomGearLootFunction implements LootFunction{

	public function __construct(
		readonly public EnchantWithLevelsLootFunction $enchant,
		readonly public float $chance
	){
		$this->chance >= 0.0 && $this->chance <= 1.0 || throw new InvalidArgumentException("'chance' must be within [0, 1], got {$this->chance}");
	}

	public function apply(Item $item, LootContext $context) : Item{
		return $context->random->nextFloat() < $this->chance ? $this->enchant->apply($item, $context) : $item;
	}
}