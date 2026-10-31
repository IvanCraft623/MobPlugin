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

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\item\ItemResolver;
use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;

/**
 * Gives a mob head the type that its data names: skull with data 1 is a wither skeleton
 * skull. The library's set_data asks for the item by its current name, which has no
 * variants, so the head is found without the data.
 */
final class MobHeadItemResolver implements ItemResolver{

	public function __construct(
		private readonly ItemResolver $inner
	){}

	public function resolve(string $name, int $meta = 0) : ?Item{
		$item = $this->inner->resolve($name);
		if($meta === 0){
			return $item;
		}

		$block = $item instanceof ItemBlock ? $item->getBlock() : null;
		if(!$block instanceof MobHead){
			return $this->inner->resolve($name, $meta);
		}

		// the types are in the order of the data
		$type = MobHeadType::cases()[$meta] ?? null;

		return $type === null ? null : $block->setMobHeadType($type)->asItem();
	}
}