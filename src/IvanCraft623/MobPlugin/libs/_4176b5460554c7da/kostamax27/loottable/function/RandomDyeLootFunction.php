<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use function count;
use function in_array;

final class RandomDyeLootFunction implements LootFunction{

	private const DYEABLE = [ItemTypeIds::LEATHER_CAP, ItemTypeIds::LEATHER_TUNIC, ItemTypeIds::LEATHER_PANTS, ItemTypeIds::LEATHER_BOOTS];

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function apply(Item $item, LootContext $context) : Item{
		if($item instanceof Armor && in_array($item->getTypeId(), self::DYEABLE, true)){
			$colors = DyeColor::cases();
			$item->setCustomColor($colors[$context->random->nextBoundedInt(count($colors))]->getRgbValue());
		}
		return $item;
	}
}