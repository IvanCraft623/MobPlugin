<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable\difficulty\RegionalDifficultyEvaluator;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable\enchant\ItemEnchanter;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable\enchant\LevelEnchanter;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class EnchantRandomGearLootFunction implements LootFunction{

	private const BASE_LEVELS = 5;
	private const RANDOM_LEVELS = 18;

	private LevelEnchanter $level_enchanter;

	public function __construct(
		ItemEnchanter $enchanter,
		readonly public RegionalDifficultyEvaluator $evaluator,
		readonly public float $chance
	){
		$this->chance >= 0.0 && $this->chance <= 1.0 || throw new InvalidArgumentException("'chance' must be within [0, 1], got {$this->chance}");
		$this->level_enchanter = new LevelEnchanter($enchanter);
	}

	public function apply(Item $item, LootContext $context) : Item{
		$difficulty = $this->evaluator->evaluate($context);
		$random = $context->random;
		if($difficulty * $this->chance <= $random->nextFloat()){
			return $item;
		}
		return $this->level_enchanter->enchant($item, (int) ($difficulty * $random->nextBoundedInt(self::RANDOM_LEVELS) + self::BASE_LEVELS), $random);
	}
}