<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\enchant;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;

interface ItemEnchanter{

	/**
	 * Returns the item that will carry the enchantments (an enchanted
	 * book for a book), or $item itself when no conversion applies.
	 * Count and named tag must survive a conversion.
	 */
	public function prepare(Item $item) : Item;

	/**
	 * Every enchantment that may sit on the result of {@see self::prepare()},
	 * as enchant_randomly picks from.
	 *
	 * @param bool $treasure whether loot-only enchantments (mending, curses, ...) are included
	 * @return list<Enchantment>
	 */
	public function availableEnchantments(Item $item, bool $treasure) : array;

	/**
	 * What an enchanting table would offer the result of {@see self::prepare()},
	 * as enchant_with_levels picks from.
	 *
	 * @param bool $treasure whether loot-only enchantments are added to the table's pool
	 * @return list<Enchantment>
	 */
	public function tableEnchantments(Item $item, bool $treasure) : array;

	/**
	 * @return Item result of {@see self::prepare()} carrying $enchantments
	 */
	public function enchant(Item $item, EnchantmentInstance ...$enchantments) : Item;
}