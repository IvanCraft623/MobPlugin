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

namespace IvanCraft623\MobPlugin\block;

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockStateStringValues;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\Model;
use pocketmine\data\bedrock\block\convert\property\IntFromRawStateMap;
use pocketmine\data\bedrock\block\convert\property\ValueFromStringProperty;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\StringToItemParser;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;

/**
 * Teaches the server the blocks of Minecraft that PocketMine-MP doesn't have, and their item forms.
 */
final class ExtraBlockRegisterHelper{

	public static function init() : void{
		self::registerBlocks();

		Server::getInstance()->getAsyncPool()->addWorkerStartHook(function(int $worker) : void{
			Server::getInstance()->getAsyncPool()->submitTaskToWorker(new class extends AsyncTask{
				public function onRun() : void{
					ExtraBlockRegisterHelper::registerBlocks();
				}
			}, $worker);
		});
	}

	public static function registerBlocks() : void{
		$block = ExtraVanillaBlocks::TURTLE_EGG();
		RuntimeBlockStateRegistry::getInstance()->register($block);

		GlobalBlockStateHandlers::getRegistrar()->mapModel(Model::create($block, BlockTypeNames::TURTLE_EGG)->properties([
			new ValueFromStringProperty(BlockStateNames::TURTLE_EGG_COUNT, IntFromRawStateMap::string([
				1 => BlockStateStringValues::TURTLE_EGG_COUNT_ONE_EGG,
				2 => BlockStateStringValues::TURTLE_EGG_COUNT_TWO_EGG,
				3 => BlockStateStringValues::TURTLE_EGG_COUNT_THREE_EGG,
				4 => BlockStateStringValues::TURTLE_EGG_COUNT_FOUR_EGG
			]), fn(TurtleEgg $b) => $b->getCount(), fn(TurtleEgg $b, int $v) => $b->setCount($v)),
			new ValueFromStringProperty(BlockStateNames::CRACKED_STATE, IntFromRawStateMap::string([
				0 => BlockStateStringValues::CRACKED_STATE_NO_CRACKS,
				1 => BlockStateStringValues::CRACKED_STATE_CRACKED,
				2 => BlockStateStringValues::CRACKED_STATE_MAX_CRACKED
			]), fn(TurtleEgg $b) => $b->getCrackStage(), fn(TurtleEgg $b, int $v) => $b->setCrackStage($v))
		]));

		GlobalItemDataHandlers::getDeserializer()->mapBlock(BlockTypeNames::TURTLE_EGG, static fn() => ExtraVanillaBlocks::TURTLE_EGG());
		GlobalItemDataHandlers::getSerializer()->mapBlock($block, static fn() => new SavedItemData(BlockTypeNames::TURTLE_EGG));

		StringToItemParser::getInstance()->registerBlock("turtle_egg", static fn() => ExtraVanillaBlocks::TURTLE_EGG());
	}
}
