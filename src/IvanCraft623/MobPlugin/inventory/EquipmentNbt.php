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

namespace IvanCraft623\MobPlugin\inventory;

use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;

/**
 * Saves a mob's gear the way vanilla Bedrock does: a list per slot group, an empty slot is an empty compound.
 */
final class EquipmentNbt{

	public const TAG_MAINHAND = "Mainhand"; //TAG_List, one item
	public const TAG_OFFHAND = "Offhand"; //TAG_List, one item
	public const TAG_ARMOR = "Armor"; //TAG_List, head, chest, legs, feet and body

	private const BODY_INDEX = 4;

	private function __construct(){
		//NOOP
	}

	public static function saveItem(Item $item) : CompoundTag{
		return $item->isNull() ? CompoundTag::create() : $item->nbtSerialize();
	}

	public static function loadItem(CompoundTag $tag) : Item{
		return $tag->count() === 0 ? VanillaItems::AIR() : Item::safeNbtDeserialize($tag, "Equipment");
	}

	public static function saveSlot(Item $item) : ListTag{
		return new ListTag([self::saveItem($item)], NBT::TAG_Compound);
	}

	public static function loadSlot(CompoundTag $nbt, string $key) : Item{
		$tag = $nbt->getListTag($key, CompoundTag::class)?->get(0);

		return $tag instanceof CompoundTag ? self::loadItem($tag) : VanillaItems::AIR();
	}

	/**
	 * @param Item $body the item in the body slot
	 */
	public static function saveArmor(ArmorInventory $armor, Item $body) : ListTag{
		$list = new ListTag([], NBT::TAG_Compound);
		for($slot = 0; $slot < $armor->getSize(); ++$slot){
			$list->push(self::saveItem($armor->getItem($slot)));
		}
		$list->push(self::saveItem($body));

		return $list;
	}

	/**
	 * The armor list of a mob that only wears something on its body: the four armor slots empty, then the body.
	 */
	public static function saveBodyArmor(Item $body) : ListTag{
		$list = new ListTag([], NBT::TAG_Compound);
		for($slot = 0; $slot < self::BODY_INDEX; ++$slot){
			$list->push(self::saveItem(VanillaItems::AIR()));
		}
		$list->push(self::saveItem($body));

		return $list;
	}

	/**
	 * Fills the armor inventory without notifying its listeners.
	 */
	public static function loadArmor(CompoundTag $nbt, ArmorInventory $armor) : void{
		$list = $nbt->getListTag(self::TAG_ARMOR, CompoundTag::class);
		if($list === null){
			return;
		}

		$items = [];
		for($slot = 0; $slot < $armor->getSize(); ++$slot){
			$tag = $list->get($slot);
			if($tag instanceof CompoundTag){
				$items[$slot] = self::loadItem($tag);
			}
		}
		self::populate($armor, $items);
	}

	public static function loadBody(CompoundTag $nbt) : Item{
		$tag = $nbt->getListTag(self::TAG_ARMOR, CompoundTag::class)?->get(self::BODY_INDEX);

		return $tag instanceof CompoundTag ? self::loadItem($tag) : VanillaItems::AIR();
	}

	/**
	 * Sets the contents without notifying the listeners.
	 *
	 * @param Item[] $items
	 * @phpstan-param array<int, Item> $items
	 */
	public static function populate(Inventory $inventory, array $items) : void{
		$listeners = $inventory->getListeners()->toArray();
		$inventory->getListeners()->clear();

		$inventory->setContents($items);

		$inventory->getListeners()->add(...$listeners);
	}
}
