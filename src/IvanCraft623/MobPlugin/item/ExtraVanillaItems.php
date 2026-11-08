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
use IvanCraft623\MobPlugin\entity\animal\Axolotl;
use IvanCraft623\MobPlugin\entity\animal\Chicken;
use IvanCraft623\MobPlugin\entity\animal\Cow;
use IvanCraft623\MobPlugin\entity\animal\MooshroomCow;
use IvanCraft623\MobPlugin\entity\animal\Pig;
use IvanCraft623\MobPlugin\entity\animal\PolarBear;
use IvanCraft623\MobPlugin\entity\animal\Rabbit;
use IvanCraft623\MobPlugin\entity\animal\Sheep;
use IvanCraft623\MobPlugin\entity\aquatic\Cod;
use IvanCraft623\MobPlugin\entity\aquatic\GlowSquid;
use IvanCraft623\MobPlugin\entity\aquatic\Pufferfish;
use IvanCraft623\MobPlugin\entity\aquatic\Salmon;
use IvanCraft623\MobPlugin\entity\aquatic\TropicalFish;
use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\entity\monster\CaveSpider;
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\entity\monster\Drowned;
use IvanCraft623\MobPlugin\entity\monster\Enderman;
use IvanCraft623\MobPlugin\entity\monster\Endermite;
use IvanCraft623\MobPlugin\entity\monster\Husk;
use IvanCraft623\MobPlugin\entity\monster\illager\Vindicator;
use IvanCraft623\MobPlugin\entity\monster\MagmaCube;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Bogged;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Skeleton;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Stray;
use IvanCraft623\MobPlugin\entity\monster\skeleton\WitherSkeleton;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\entity\monster\Spider;
use IvanCraft623\MobPlugin\entity\monster\ZombifiedPiglin;
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
 * @method static MobBucket AXOLOTL_BUCKET()
 * @method static \pocketmine\item\SpawnEgg AXOLOTL_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg HUSK_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg COD_SPAWN_EGG()
 * @method static MobBucket COD_BUCKET()
 * @method static \pocketmine\item\SpawnEgg SALMON_SPAWN_EGG()
 * @method static MobBucket SALMON_BUCKET()
 * @method static \pocketmine\item\SpawnEgg TROPICAL_FISH_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg GLOW_SQUID_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg MAGMA_CUBE_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg BOGGED_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg VINDICATOR_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg ZOMBIE_PIGMAN_SPAWN_EGG()
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
 * @method static MobBucket TROPICAL_FISH_BUCKET()
 * @method static \pocketmine\item\SpawnEgg PUFFERFISH_SPAWN_EGG()
 * @method static MobBucket PUFFERFISH_BUCKET()
 * @method static \pocketmine\item\SpawnEgg DROWNED_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg POLAR_BEAR_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg RABBIT_SPAWN_EGG()
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
		self::registerBuckets();
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
		self::register("axolotl_spawn_egg", new MobSpawnEgg(new IID(Ids::AXOLOTL_SPAWN_EGG()), "Axolotl Spawn Egg", Axolotl::class));
		self::register("husk_spawn_egg", new MobSpawnEgg(new IID(Ids::HUSK_SPAWN_EGG()), "Husk Spawn Egg", Husk::class));
		self::register("cod_spawn_egg", new MobSpawnEgg(new IID(Ids::COD_SPAWN_EGG()), "Cod Spawn Egg", Cod::class));
		self::register("salmon_spawn_egg", new MobSpawnEgg(new IID(Ids::SALMON_SPAWN_EGG()), "Salmon Spawn Egg", Salmon::class));
		self::register("tropical_fish_spawn_egg", new MobSpawnEgg(new IID(Ids::TROPICAL_FISH_SPAWN_EGG()), "Tropical Fish Spawn Egg", TropicalFish::class));
		self::register("glow_squid_spawn_egg", new MobSpawnEgg(new IID(Ids::GLOW_SQUID_SPAWN_EGG()), "Glow Squid Spawn Egg", GlowSquid::class));
		self::register("magma_cube_spawn_egg", new MobSpawnEgg(new IID(Ids::MAGMA_CUBE_SPAWN_EGG()), "Magma Cube Spawn Egg", MagmaCube::class));
		self::register("bogged_spawn_egg", new MobSpawnEgg(new IID(Ids::BOGGED_SPAWN_EGG()), "Bogged Spawn Egg", Bogged::class));
		self::register("vindicator_spawn_egg", new MobSpawnEgg(new IID(Ids::VINDICATOR_SPAWN_EGG()), "Vindicator Spawn Egg", Vindicator::class));
		self::register("zombie_pigman_spawn_egg", new MobSpawnEgg(new IID(Ids::ZOMBIE_PIGMAN_SPAWN_EGG()), "Zombified Piglin Spawn Egg", ZombifiedPiglin::class));
		self::register("pufferfish_spawn_egg", new MobSpawnEgg(new IID(Ids::PUFFERFISH_SPAWN_EGG()), "Pufferfish Spawn Egg", Pufferfish::class));
		self::register("drowned_spawn_egg", new MobSpawnEgg(new IID(Ids::DROWNED_SPAWN_EGG()), "Drowned Spawn Egg", Drowned::class));
		self::register("polar_bear_spawn_egg", new MobSpawnEgg(new IID(Ids::POLAR_BEAR_SPAWN_EGG()), "Polar Bear Spawn Egg", PolarBear::class));
		self::register("rabbit_spawn_egg", new MobSpawnEgg(new IID(Ids::RABBIT_SPAWN_EGG()), "Rabbit Spawn Egg", Rabbit::class));
	}

	private static function registerBuckets() : void{
		self::register("cod_bucket", new MobBucket(new IID(Ids::COD_BUCKET()), "Bucket of Cod", Cod::class));
		self::register("salmon_bucket", new MobBucket(new IID(Ids::SALMON_BUCKET()), "Bucket of Salmon", Salmon::class));
		self::register("axolotl_bucket", new MobBucket(new IID(Ids::AXOLOTL_BUCKET()), "Bucket of Axolotl", Axolotl::class));
		self::register("tropical_fish_bucket", new MobBucket(new IID(Ids::TROPICAL_FISH_BUCKET()), "Bucket of Tropical Fish", TropicalFish::class));
		self::register("pufferfish_bucket", new MobBucket(new IID(Ids::PUFFERFISH_BUCKET()), "Bucket of Pufferfish", Pufferfish::class));
	}
}
