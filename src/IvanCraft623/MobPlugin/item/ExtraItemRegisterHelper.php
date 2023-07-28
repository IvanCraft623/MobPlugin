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

namespace IvanCraft623\MobPlugin\item;

use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalItemDataHandlers;

final class ExtraItemRegisterHelper{

	public static function init() : void{
		self::registerItems();

		Server::getInstance()->getAsyncPool()->addWorkerStartHook(function(int $worker) : void{
			Server::getInstance()->getAsyncPool()->submitTaskToWorker(new class extends AsyncTask{
				public function onRun() : void{
					ExtraItemRegisterHelper::registerItems();
				}
			}, $worker);
		});
	}

	public static function registerItems() : void{
		self::registerSimpleItem(ItemTypeNames::ENDERMITE_SPAWN_EGG, ExtraVanillaItems::ENDERMITE_SPAWN_EGG(), ["endermite_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::MOOSHROOM_SPAWN_EGG, ExtraVanillaItems::MOOSHROOM_SPAWN_EGG(), ["mooshroom_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::COW_SPAWN_EGG, ExtraVanillaItems::COW_SPAWN_EGG(), ["cow_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::SHEEP_SPAWN_EGG, ExtraVanillaItems::SHEEP_SPAWN_EGG(), ["sheep_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::CREEPER_SPAWN_EGG, ExtraVanillaItems::CREEPER_SPAWN_EGG(), ["creeper_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::CHICKEN_SPAWN_EGG, ExtraVanillaItems::CHICKEN_SPAWN_EGG(), ["chicken_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::PIG_SPAWN_EGG, ExtraVanillaItems::PIG_SPAWN_EGG(), ["pig_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::BAT_SPAWN_EGG, ExtraVanillaItems::BAT_SPAWN_EGG(), ["bat_spawn_egg"]);
		self::registerSimpleItem(ItemTypeNames::SLIME_SPAWN_EGG, ExtraVanillaItems::SLIME_SPAWN_EGG(), ["slime_spawn_egg"]);
	}

	/**
	 * @param string[] $stringToItemParserNames
	 */
	private static function registerSimpleItem(string $id, Item $item, array $stringToItemParserNames) : void{
		GlobalItemDataHandlers::getDeserializer()->map($id, fn() => clone $item);
		GlobalItemDataHandlers::getSerializer()->map($item, fn() => new SavedItemData($id));

		foreach($stringToItemParserNames as $name){
			StringToItemParser::getInstance()->register($name, fn() => clone $item);
		}
	}
}
