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
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\entity\monster\Enderman;
use IvanCraft623\MobPlugin\entity\monster\Endermite;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\item\ExtraItemTypeIds as Ids;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier as IID;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\utils\CloningRegistryTrait;
use pocketmine\world\World;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static \pocketmine\item\SpawnEgg BAT_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg CHICKEN_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg COW_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg CREEPER_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg ENDERMAN_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg ENDERMITE_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg MOOSHROOM_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg PIG_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg SHEEP_SPAWN_EGG()
 * @method static \pocketmine\item\SpawnEgg SLIME_SPAWN_EGG()
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
		self::register("endermite_spawn_egg", new class(new IID(Ids::ENDERMITE_SPAWN_EGG()), "Endermite Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Endermite(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("mooshroom_spawn_egg", new class(new IID(Ids::MOOSHROOM_SPAWN_EGG()), "Mooshroom Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new MooshroomCow(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("cow_spawn_egg", new class(new IID(Ids::COW_SPAWN_EGG()), "Cow Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Cow(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("sheep_spawn_egg", new class(new IID(Ids::SHEEP_SPAWN_EGG()), "Sheep Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Sheep(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("creeper_spawn_egg", new class(new IID(Ids::CREEPER_SPAWN_EGG()), "Creeper Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Creeper(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("chicken_spawn_egg", new class(new IID(Ids::CHICKEN_SPAWN_EGG()), "Chicken Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Chicken(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("pig_spawn_egg", new class(new IID(Ids::PIG_SPAWN_EGG()), "Pig Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Pig(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("bat_spawn_egg", new class(new IID(Ids::BAT_SPAWN_EGG()), "Bat Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Bat(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("slime_spawn_egg", new class(new IID(Ids::SLIME_SPAWN_EGG()), "Slime Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Slime(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});

		self::register("enderman_spawn_egg", new class(new IID(Ids::ENDERMAN_SPAWN_EGG()), "Enderman Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return (new Enderman(Location::fromObject($pos, $world, $yaw, $pitch)))->setPersistent();
			}
		});
	}
}
