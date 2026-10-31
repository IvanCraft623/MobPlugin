<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\loot;

use IvanCraft623\MobPlugin\utils\Utils;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\enchant\ItemEnchanter;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function\EnchantWithLevelsLootFunction;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function\LootFunction;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

final class RandomGearEnchantLootFunction implements LootFunction{

	private const BASE_LEVEL = 5;

	private const LEVEL_RANGE = 18;

	public function __construct(
		private readonly ItemEnchanter $enchanter,
		private readonly float $chance
	){}

	public function apply(Item $item, LootContext $context) : Item{
		$random = $context->random;
		$world = $context->entity?->getWorld();
		$difficulty = $world === null ? 0.0 : Utils::getRegionalDifficulty($world);
		if($difficulty * $this->chance <= $random->nextFloat()){
			return $item;
		}

		$level = (int) ($difficulty * $random->nextBoundedInt(self::LEVEL_RANGE) + self::BASE_LEVEL);

		return (new EnchantWithLevelsLootFunction($this->enchanter, IntRange::exact($level)))->apply($item, $context);
	}
}