<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

final class SpecificEnchant{

	public function __construct(
		readonly public Enchantment $enchantment,
		readonly public IntRange $level = new IntRange(1, 1)
	){
		$this->level->min >= 1 || throw new InvalidArgumentException("Enchantment level must be >= 1, got {$this->level->min}");
		$this->level->max <= $this->enchantment->getMaxLevel() || throw new InvalidArgumentException("Enchantment level must be <= {$this->enchantment->getMaxLevel()}, got {$this->level->max}");
	}

	public function roll(LootContext $context) : EnchantmentInstance{
		return new EnchantmentInstance($this->enchantment, $this->level->sample($context->random));
	}
}