<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\enchant\ItemEnchanter;
use IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\enchant\LevelEnchanter;
use IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class EnchantWithLevelsLootFunction implements LootFunction{

	private LevelEnchanter $level_enchanter;

	public function __construct(
		readonly public ItemEnchanter $enchanter,
		readonly public IntRange $levels,
		readonly public bool $treasure = false
	){
		$this->levels->min >= 0 || throw new InvalidArgumentException("'levels' must be >= 0, got {$this->levels->min}");
		$this->level_enchanter = new LevelEnchanter($this->enchanter, $this->treasure);
	}

	public function apply(Item $item, LootContext $context) : Item{
		return $this->level_enchanter->enchant($item, $this->levels->sample($context->random), $context->random);
	}
}