<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\condition\LootCondition;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class ConditionalLootFunction implements LootFunction{

	/**
	 * @param list<LootCondition> $conditions
	 */
	public function __construct(
		readonly public LootFunction $function,
		readonly public array $conditions
	){}

	public function apply(Item $item, LootContext $context) : Item{
		foreach($this->conditions as $condition){
			if(!$condition->test($context)){
				return $item;
			}
		}
		return $this->function->apply($item, $context);
	}
}