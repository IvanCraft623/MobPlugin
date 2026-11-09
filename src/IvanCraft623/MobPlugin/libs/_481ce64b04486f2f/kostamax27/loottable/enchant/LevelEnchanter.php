<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\kostamax27\loottable\enchant;

use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\utils\Random;
use function array_filter;
use function count;
use function round;

/**
 * Enchants an item the way an enchanting table does at a given level, whatever the level comes from.
 */
final class LevelEnchanter{

	public function __construct(
		readonly public ItemEnchanter $enchanter,
		readonly public bool $treasure = false
	){}

	public function enchant(Item $item, int $levels, Random $random) : Item{
		$prepared = $this->enchanter->prepare($item);
		$enchantability = $prepared->getEnchantability();
		$power = $levels + $random->nextRange(0, $enchantability >> 2) + $random->nextRange(0, $enchantability >> 2) + 1;
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