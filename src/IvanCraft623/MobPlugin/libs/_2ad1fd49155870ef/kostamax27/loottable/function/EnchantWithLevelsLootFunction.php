<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\enchant\ItemEnchanter;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\utils\Random;
use function array_filter;
use function count;
use function round;

final class EnchantWithLevelsLootFunction implements LootFunction{

	public function __construct(
		readonly public ItemEnchanter $enchanter,
		readonly public IntRange $levels,
		readonly public bool $treasure = false
	){
		$this->levels->min >= 0 || throw new InvalidArgumentException("'levels' must be >= 0, got {$this->levels->min}");
	}

	public function apply(Item $item, LootContext $context) : Item{
		$random = $context->random;
		$prepared = $this->enchanter->prepare($item);
		$enchantability = $prepared->getEnchantability();
		$power = $this->levels->sample($random) + $random->nextRange(0, $enchantability >> 2) + $random->nextRange(0, $enchantability >> 2) + 1;
		$power = (int) round($power * (1 + ($random->nextFloat() + $random->nextFloat() - 1) * 0.15));

		$available = [];
		foreach($this->enchanter->tableEnchantments($prepared, $this->treasure) as $enchantment){
			for($level = $enchantment->getMaxLevel(); $level > 0; --$level){
				if($power >= $enchantment->getMinEnchantingPower($level) && $power <= $enchantment->getMaxEnchantingPower($level)){
					$available[] = new EnchantmentInstance($enchantment, $level);
					break;
				}
			}
		}

		$result = [];
		$last = self::weighted($random, $available);
		while($last !== null){
			$result[] = $last;
			if($random->nextFloat() > ($power + 1) / 50){
				break;
			}
			$available = array_filter($available, static fn(EnchantmentInstance $e) : bool => $e->getType() !== $last->getType() && $e->getType()->isCompatibleWith($last->getType()));
			$last = self::weighted($random, $available);
			$power >>= 1;
		}
		return count($result) === 0 ? $item : $this->enchanter->enchant($item, ...$result);
	}

	/**
	 * @param array<int, EnchantmentInstance> $enchantments
	 */
	private static function weighted(Random $random, array $enchantments) : ?EnchantmentInstance{
		if(count($enchantments) === 0){
			return null;
		}
		$total = 0;
		foreach($enchantments as $enchantment){
			$total += $enchantment->getType()->getRarity();
		}
		$pick = $random->nextRange(1, $total);
		foreach($enchantments as $enchantment){
			if(($pick -= $enchantment->getType()->getRarity()) <= 0){
				return $enchantment;
			}
		}
		return null;
	}
}