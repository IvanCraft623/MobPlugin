<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\kostamax27\loottable\looting;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\kostamax27\loottable\LootContext;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\StringToEnchantmentParser;

final class EnchantmentLootingEvaluator implements LootingEvaluator{

	public static function fromName(string $name = "looting") : self{
		return new self(StringToEnchantmentParser::getInstance()->parse($name) ?? throw new InvalidArgumentException("Unknown enchantment '{$name}'"));
	}

	public function __construct(
		readonly public Enchantment $enchantment
	){}

	public function evaluate(LootContext $context) : int{
		return $context->tool?->getEnchantmentLevel($this->enchantment) ?? 0;
	}
}