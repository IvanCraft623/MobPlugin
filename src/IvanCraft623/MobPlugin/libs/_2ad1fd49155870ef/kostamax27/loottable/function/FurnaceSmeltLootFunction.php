<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\crafting\FurnaceRecipeManager;
use pocketmine\item\Item;

final class FurnaceSmeltLootFunction implements LootFunction{

	public function __construct(
		readonly private FurnaceRecipeManager $recipe_manager
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$recipe = $this->recipe_manager->match($item);
		if($recipe === null){
			return $item;
		}
		$result = $recipe->getResult();
		$result->setCount($item->getCount());
		return $result;
	}
}