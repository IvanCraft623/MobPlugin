<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\entity\EntityInspector;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\LootContext;
use pocketmine\block\utils\Colored;
use pocketmine\item\Banner;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;

final class SetDataFromColorIndexLootFunction implements LootFunction{

	public function __construct(
		readonly public EntityInspector $inspector
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$color = $context->entity !== null ? $this->inspector->color($context->entity) : null;
		if($color === null){
			return $item;
		}
		if($item instanceof Dye || $item instanceof Banner){
			return $item->setColor($color);
		}
		$block = $item instanceof ItemBlock ? $item->getBlock() : null;
		if(!$block instanceof Colored){
			return $item;
		}
		return $block->setColor($color)->asItem()
			->setNamedTag($item->getNamedTag())
			->setCount($item->getCount());
	}
}