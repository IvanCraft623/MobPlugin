<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\enchant;

use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use function array_filter;
use function array_merge;
use function array_values;
use function count;

final class VanillaItemEnchanter implements ItemEnchanter{

	public function __construct(){
	}

	public function prepare(Item $item) : Item{
		if($item->getTypeId() !== ItemTypeIds::BOOK){
			return $item;
		}
		return VanillaItems::ENCHANTED_BOOK()->setNamedTag($item->getNamedTag())->setCount($item->getCount());
	}

	public function availableEnchantments(Item $item, bool $treasure) : array{
		$registry = AvailableEnchantmentRegistry::getInstance();
		$enchantments = $registry->getAllEnchantmentsForItem($this->prepare($item));
		if(!$treasure){
			$enchantments = array_filter($enchantments, static fn(Enchantment $e) : bool => count($registry->getPrimaryItemTags($e)) > 0);
		}
		return array_values($enchantments);
	}

	public function tableEnchantments(Item $item, bool $treasure) : array{
		$registry = AvailableEnchantmentRegistry::getInstance();
		$item = $this->prepare($item);
		$enchantments = $registry->getPrimaryEnchantmentsForItem($item);
		if($treasure){
			$enchantments = array_merge($enchantments, array_filter($registry->getAllEnchantmentsForItem($item), static fn(Enchantment $e) : bool => count($registry->getPrimaryItemTags($e)) === 0));
		}
		return array_values($enchantments);
	}

	public function enchant(Item $item, EnchantmentInstance ...$enchantments) : Item{
		$item = $this->prepare($item);
		foreach($enchantments as $enchantment){
			$item->addEnchantment($enchantment);
		}
		return $item;
	}
}