<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\FloatRange;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use function round;

final class SetDamageLootFunction implements LootFunction{

	public function __construct(
		readonly public FloatRange $damage
	){
		$this->damage->min >= 0.0 && $this->damage->max <= 1.0 || throw new InvalidArgumentException("'damage' must be within [0, 1], got [{$this->damage->min}, {$this->damage->max}]");
	}

	public function apply(Item $item, LootContext $context) : Item{
		if($item instanceof Durable){
			$item->setDamage((int) round($item->getMaxDurability() * (1.0 - $this->damage->sample($context->random))));
		}
		return $item;
	}
}