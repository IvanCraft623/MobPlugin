<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\item\Item;
use pocketmine\item\Potion;
use pocketmine\item\PotionType;
use pocketmine\item\SplashPotion;

final class SetPotionLootFunction implements LootFunction{

	public function __construct(
		readonly public PotionType $type
	){}

	public function apply(Item $item, LootContext $context) : Item{
		if($item instanceof Potion || $item instanceof SplashPotion){
			$item->setType($this->type);
		}
		return $item;
	}
}