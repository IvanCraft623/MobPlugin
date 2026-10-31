<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\item\Item;
use pocketmine\item\SuspiciousStew;
use pocketmine\item\SuspiciousStewType;
use function count;

final class SetStewEffectLootFunction implements LootFunction{

	/**
	 * @param non-empty-list<SuspiciousStewType> $types
	 */
	public function __construct(
		readonly public array $types
	){
		count($this->types) > 0 || throw new InvalidArgumentException("'effects' must not be empty");
	}

	public function apply(Item $item, LootContext $context) : Item{
		if($item instanceof SuspiciousStew){
			$item->setType($this->types[$context->random->nextBoundedInt(count($this->types))]);
		}
		return $item;
	}
}