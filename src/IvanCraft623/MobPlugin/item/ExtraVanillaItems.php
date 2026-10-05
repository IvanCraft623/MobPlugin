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

use IvanCraft623\MobPlugin\entity\ambient\Bat;
use IvanCraft623\MobPlugin\entity\animal\Chicken;
use IvanCraft623\MobPlugin\entity\animal\Cow;
use IvanCraft623\MobPlugin\entity\animal\MooshroomCow;
use IvanCraft623\MobPlugin\entity\animal\Pig;
use IvanCraft623\MobPlugin\entity\animal\Sheep;
use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\entity\monster\CaveSpider;
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\entity\monster\Enderman;
use IvanCraft623\MobPlugin\entity\monster\Endermite;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Skeleton;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Stray;
use IvanCraft623\MobPlugin\entity\monster\skeleton\WitherSkeleton;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\entity\monster\Spider;
use IvanCraft623\MobPlugin\item\ExtraItemTypeIds as Ids;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier as IID;
use pocketmine\utils\CloningRegistryTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static \pocketmine\item\SpawnEgg BAT_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg CAVE_SPIDER_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg CHICKEN_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg COW_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg CREEPER_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg ENDERMAN_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg ENDERMITE_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg IRON_GOLEM_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg MOOSHROOM_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg PIG_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg SHEEP_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg SKELETON_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg SLIME_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg SNOW_GOLEM_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg SPIDER_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg STRAY_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg WITHER_SKELETON_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg WITHER_SPAWN_EGG()
 */
final class ExtraVanillaItems{
	use CloningRegistryTrait;

	private function __construct(){
		//NOOP
	}

	protected static function register(string $name, Item $item) : void{
		self::_registryRegister($name, $item);
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<string, Item>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Item[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		self::registerSpawnEggs();
	}

	private static function registerSpawnEggs() : void{
		self::register("endermite_spawn_egg", new MobSpawnEgg(new IID(Ids::ENDERMITE_SPAWN_EGG()), "Endermite Spawn Egg", Endermite::class));
		self::register("mooshroom_spawn_egg", new MobSpawnEgg(new IID(Ids::MOOSHROOM_SPAWN_EGG()), "Mooshroom Spawn Egg", MooshroomCow::class));
		self::register("cow_spawn_egg", new MobSpawnEgg(new IID(Ids::COW_SPAWN_EGG()), "Cow Spawn Egg", Cow::class));
		self::register("sheep_spawn_egg", new MobSpawnEgg(new IID(Ids::SHEEP_SPAWN_EGG()), "Sheep Spawn Egg", Sheep::class));
		self::register("creeper_spawn_egg", new MobSpawnEgg(new IID(Ids::CREEPER_SPAWN_EGG()), "Creeper Spawn Egg", Creeper::class));
		self::register("chicken_spawn_egg", new MobSpawnEgg(new IID(Ids::CHICKEN_SPAWN_EGG()), "Chicken Spawn Egg", Chicken::class));
		self::register("pig_spawn_egg", new MobSpawnEgg(new IID(Ids::PIG_SPAWN_EGG()), "Pig Spawn Egg", Pig::class));
		self::register("bat_spawn_egg", new MobSpawnEgg(new IID(Ids::BAT_SPAWN_EGG()), "Bat Spawn Egg", Bat::class));
		self::register("slime_spawn_egg", new MobSpawnEgg(new IID(Ids::SLIME_SPAWN_EGG()), "Slime Spawn Egg", Slime::class));
		self::register("enderman_spawn_egg", new MobSpawnEgg(new IID(Ids::ENDERMAN_SPAWN_EGG()), "Enderman Spawn Egg", Enderman::class));
		self::register("spider_spawn_egg", new MobSpawnEgg(new IID(Ids::SPIDER_SPAWN_EGG()), "Spider Spawn Egg", Spider::class));
		self::register("cave_spider_spawn_egg", new MobSpawnEgg(new IID(Ids::CAVE_SPIDER_SPAWN_EGG()), "Cave Spider Spawn Egg", CaveSpider::class));
		self::register("iron_golem_spawn_egg", new MobSpawnEgg(new IID(Ids::IRON_GOLEM_SPAWN_EGG()), "Iron Golem Spawn Egg", IronGolem::class));
		self::register("snow_golem_spawn_egg", new MobSpawnEgg(new IID(Ids::SNOW_GOLEM_SPAWN_EGG()), "Snow Golem Spawn Egg", SnowGolem::class));
		self::register("skeleton_spawn_egg", new MobSpawnEgg(new IID(Ids::SKELETON_SPAWN_EGG()), "Skeleton Spawn Egg", Skeleton::class));
		self::register("stray_spawn_egg", new MobSpawnEgg(new IID(Ids::STRAY_SPAWN_EGG()), "Stray Spawn Egg", Stray::class));
		self::register("wither_skeleton_spawn_egg", new MobSpawnEgg(new IID(Ids::WITHER_SKELETON_SPAWN_EGG()), "Wither Skeleton Spawn Egg", WitherSkeleton::class));
		self::register("wither_spawn_egg", new MobSpawnEgg(new IID(Ids::WITHER_SPAWN_EGG()), "Wither Spawn Egg", Wither::class));
	}
}
