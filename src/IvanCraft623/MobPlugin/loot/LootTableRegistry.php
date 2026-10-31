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

use InvalidArgumentException;
use kostamax27\loottable\condition\LootCondition;
use kostamax27\loottable\condition\LootConditionRegistry;
use kostamax27\loottable\function\LootFunction;
use kostamax27\loottable\function\LootFunctionRegistry;
use kostamax27\loottable\item\ChainedItemResolver;
use kostamax27\loottable\LootData;
use kostamax27\loottable\looting\EnchantmentLootingEvaluator;
use kostamax27\loottable\looting\NullLootingEvaluator;
use kostamax27\loottable\LootTable as LibraryLootTable;
use kostamax27\loottable\LootTableFactory;
use kostamax27\loottable\LootTableResolver;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function json_decode;
use function strval;
use const JSON_THROW_ON_ERROR;

/**
 * The vanilla loot tables, built when first rolled.
 *
 * Item and enchantment names go through what the server has registered by then, so tables
 * built after every plugin is enabled see the items and enchantments those plugins add.
 * Call reload() after registering one later.
 */
final class LootTableRegistry implements LootTableResolver{
	use SingletonTrait;

	private const LOOTING_ENCHANTMENT = "looting";

	private const COLOR_FUNCTION = "set_data_from_color_index";

	private const GEAR_ENCHANT_FUNCTION = "enchant_random_gear";

	private const REGIONAL_DIFFICULTY_CONDITION = "random_regional_difficulty_chance";

	/** @phpstan-var array<array-key, mixed> table name => table as written in the data */
	private array $data = [];

	/** @phpstan-var array<string, LibraryLootTable> */
	private array $tables = [];

	/** @phpstan-var array<string, true> */
	private array $resolving = [];

	private ?LootTableFactory $factory = null;

	private ?AirFallbackItemResolver $items = null;

	/**
	 * @throws \RuntimeException if the file can't be read
	 * @throws \JsonException if it isn't valid JSON
	 */
	public function registerVanilla(string $lootTablesPath) : void{
		$this->data = (array) json_decode(Filesystem::fileGetContents($lootTablesPath), true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * @phpstan-return list<string> the names of all the tables
	 */
	public function getNames() : array{
		return array_map(strval(...), array_keys($this->data));
	}

	/**
	 * Forgets the built tables, so the next roll sees the items and enchantments registered since.
	 */
	public function reload() : void{
		$this->tables = [];
		$this->factory = null;
		$this->items = null;
	}

	/**
	 * Builds every table, so the first roll doesn't pay for the item lookup tables.
	 *
	 * @phpstan-return list<string> what the server lacks for the tables, for the caller to log
	 */
	public function warmUp() : array{
		$problems = [];
		foreach($this->getNames() as $name){
			try{
				$this->resolve($name);
			}catch(InvalidArgumentException $e){
				$problems[] = "Loot table \"$name\" is unavailable: " . $e->getMessage();
			}
		}
		$missing = $this->items?->getMissing() ?? [];
		if(count($missing) !== 0){
			$problems[] = count($missing) . " item(s) named by loot tables are not available, so their drops are skipped: " . implode(", ", $missing);
		}

		return $problems;
	}

	/**
	 * @param string $name the table's path in the vanilla data without "loot_tables/" and ".json", like "entities/cow"
	 *
	 * @throws InvalidArgumentException if there is no such table, or it can't be built
	 */
	public function resolve(string $name) : LibraryLootTable{
		if(isset($this->tables[$name])){
			return $this->tables[$name];
		}
		isset($this->resolving[$name]) && throw new InvalidArgumentException("Loot table \"$name\" references itself");
		$data = (new LootData($this->data, ""))->object($name);

		$this->resolving[$name] = true;
		try{
			return $this->tables[$name] = $this->getFactory()->fromData($data);
		}finally{
			unset($this->resolving[$name]);
		}
	}

	private function getFactory() : LootTableFactory{
		if($this->factory !== null){
			return $this->factory;
		}

		$looting = StringToEnchantmentParser::getInstance()->parse(self::LOOTING_ENCHANTMENT);
		$this->items = $this->createItemResolver();
		$factory = new LootTableFactory(
			$this->createFunctions(),
			$this->createConditions(),
			$this->items,
			$looting !== null ? new EnchantmentLootingEvaluator($looting) : NullLootingEvaluator::instance()
		);
		$factory->setTableResolver($this);

		return $this->factory = $factory;
	}

	private function createItemResolver() : AirFallbackItemResolver{
		return new AirFallbackItemResolver(new MobHeadItemResolver(ChainedItemResolver::createDefault()));
	}

	private function createFunctions() : LootFunctionRegistry{
		$functions = LootFunctionRegistry::createDefault();
		$functions->unregister(self::COLOR_FUNCTION);
		$functions->register(self::COLOR_FUNCTION, static fn() : LootFunction => new EntityColorLootFunction());
		$functions->unregister(self::GEAR_ENCHANT_FUNCTION);
		$functions->register(self::GEAR_ENCHANT_FUNCTION, static fn(LootData $data, LootTableFactory $factory) : LootFunction => new RandomGearEnchantLootFunction(
			$factory->item_enchanter,
			$data->floatOr("chance", 1.0)
		));

		return $functions;
	}

	private function createConditions() : LootConditionRegistry{
		$conditions = LootConditionRegistry::createDefault();
		$conditions->unregister(self::REGIONAL_DIFFICULTY_CONDITION);
		$conditions->register(self::REGIONAL_DIFFICULTY_CONDITION, static fn(LootData $data) : LootCondition => new RegionalDifficultyLootCondition($data->floatOr("max_chance", 0.0)));

		return $conditions;
	}
}
