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

use pocketmine\item\ItemTypeIds;

use function count;
use function mb_strtoupper;
use function preg_match;

/**
 * Every item in {@link ExtraVanillaItems} has a corresponding constant in this class. These constants can be used to
 * identify and compare item types efficiently using {@link Item::getTypeId()}.
 *
 * @method static int BAT_SPAWN_EGG()
 * @method static int CHICKEN_SPAWN_EGG()
 * @method static int COW_SPAWN_EGG()
 * @method static int CREEPER_SPAWN_EGG()
 * @method static int ENDERMAN_SPAWN_EGG()
 * @method static int ENDERMITE_SPAWN_EGG()
 * @method static int MOOSHROOM_SPAWN_EGG()
 * @method static int PIG_SPAWN_EGG()
 * @method static int SHEEP_SPAWN_EGG()
 * @method static int SLIME_SPAWN_EGG()
 */
final class ExtraItemTypeIds{
	/**
	 * @var int[]
	 * @phpstan-var array<string, int>
	 */
	private static $members = null;

	protected static function setup() : void {
		self::register("endermite_spawn_egg");
		self::register("mooshroom_spawn_egg");
		self::register("cow_spawn_egg");
		self::register("sheep_spawn_egg");
		self::register("creeper_spawn_egg");
		self::register("chicken_spawn_egg");
		self::register("pig_spawn_egg");
		self::register("bat_spawn_egg");
		self::register("slime_spawn_egg");
		self::register("enderman_spawn_egg");
	}

	private static function verifyName(string $name) : void{
		if(preg_match('/^(?!\d)[A-Za-z\d_]+$/u', $name) === 0){
			throw new \InvalidArgumentException("Invalid member name \"$name\", should only contain letters, numbers and underscores, and must not start with a number");
		}
	}

	/**
	 * Adds the given typeId to the registry.
	 *
	 * @throws \InvalidArgumentException
	 */
	private static function register(string $name) : void{
		self::verifyName($name);
		$upperName = mb_strtoupper($name);
		if(isset(self::$members[$upperName])){
			throw new \InvalidArgumentException("\"$upperName\" is already reserved");
		}
		self::$members[$upperName] = ItemTypeIds::newId();
	}

	/**
	 * @internal Lazy-inits the enum if necessary.
	 *
	 * @throws \InvalidArgumentException
	 */
	protected static function checkInit() : void{
		if(self::$members === null){
			self::$members = [];
			self::setup();
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private static function _registryFromString(string $name) : int{
		self::checkInit();
		$upperName = mb_strtoupper($name);
		if(!isset(self::$members[$upperName])){
			throw new \InvalidArgumentException("No such registry member: " . self::class . "::" . $upperName);
		}
		return self::$members[$upperName];
	}

	/**
	 * @param string  $name
	 * @param mixed[] $arguments
	 * @phpstan-param list<mixed> $arguments
	 *
	 * @return int
	 */
	public static function __callStatic($name, $arguments){
		if(count($arguments) > 0){
			throw new \ArgumentCountError("Expected exactly 0 arguments, " . count($arguments) . " passed");
		}
		try{
			return self::_registryFromString($name);
		}catch(\InvalidArgumentException $e){
			throw new \Error($e->getMessage(), 0, $e);
		}
	}
}
