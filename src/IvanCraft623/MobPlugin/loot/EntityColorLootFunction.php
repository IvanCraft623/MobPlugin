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

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function\LootFunction;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\block\utils\Colored;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;

/**
 * Gives a colored block item the color of the colored entity that drops it: a sheep's wool.
 */
final class EntityColorLootFunction implements LootFunction{

	public function apply(Item $item, LootContext $context) : Item{
		$entity = $context->entity;
		$block = $item instanceof ItemBlock ? $item->getBlock() : null;
		if(!$entity instanceof Colored || !$block instanceof Colored){
			return $item;
		}

		return $block->setColor($entity->getColor())->asItem()->setCount($item->getCount());
	}
}