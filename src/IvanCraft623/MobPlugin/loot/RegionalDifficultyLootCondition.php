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
use kostamax27\loottable\condition\LootCondition;
use kostamax27\loottable\LootContext;

final class RegionalDifficultyLootCondition implements LootCondition{

	public function __construct(
		private readonly float $maxChance
	){}

	public function test(LootContext $context) : bool{
		$world = $context->entity?->getWorld();

		return $world !== null && Utils::getRegionalDifficulty($world) * $this->maxChance > $context->random->nextFloat();
	}
}
