<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\kostamax27\loottable\item\ItemResolver;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\kostamax27\loottable\LootContext;
use pocketmine\data\bedrock\item\ItemTypeSerializeException;
use pocketmine\item\Item;
use pocketmine\world\format\io\GlobalItemDataHandlers;

final class SetDataLootFunction implements LootFunction{

	/**
	 * @param string|null $name item name as written in the loot table, the data is relative to it. Null uses the item's own name
	 */
	public function __construct(
		readonly public ItemResolver $resolver,
		readonly public IntRange $data,
		readonly public ?string $name = null
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$name = $this->name;
		if($name === null){
			try{
				$name = GlobalItemDataHandlers::getSerializer()->serializeType($item)->getName();
			}catch(ItemTypeSerializeException){
				return $item;
			}
		}
		$result = $this->resolver->resolve($name, $this->data->sample($context->random));
		if($result === null){
			return $item;
		}
		return $result->setNamedTag($item->getNamedTag())->setCount($item->getCount());
	}
}