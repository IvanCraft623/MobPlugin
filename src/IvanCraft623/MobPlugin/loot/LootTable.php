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

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\item\Item;

/**
 * A vanilla loot table, built when first rolled.
 */
final class LootTable{

	/**
	 * @param string $name the table's path in the vanilla data without "loot_tables/" and ".json", like "entities/cow"
	 */
	public function __construct(
		public readonly string $name
	){}

	/**
	 * @return Item[]
	 *
	 * @throws \InvalidArgumentException if the table can't be built
	 */
	public function generate(LootContext $context) : array{
		return LootTableRegistry::getInstance()->resolve($this->name)->generate($context);
	}
}