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

namespace IvanCraft623\MobPlugin;

use IvanCraft623\MobPlugin\entity\animal\Cow;
use IvanCraft623\MobPlugin\entity\animal\MooshroomCow;
use IvanCraft623\MobPlugin\entity\animal\Sheep;
use IvanCraft623\MobPlugin\entity\CustomAttributes;
use IvanCraft623\MobPlugin\entity\monster\Endermite;

use pocketmine\data\bedrock\EntityLegacyIds as LegacyIds;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper as Helper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier as IID;
use pocketmine\item\ItemIds as Ids;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Random;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function mt_rand;

class MobPlugin extends PluginBase {
	use SingletonTrait;

	private ?Random $random = null;

	public function onLoad() : void {
		self::setInstance($this);
	}

	public function onEnable() : void {
		$this->registerAttributes();
		$this->registerEntities();
		$this->registerSpawnEggs();
	}

	public function getRandom() : Random {
		if ($this->random === null) {
			$this->random = new Random(mt_rand());
		}
		return $this->random;
	}

	private function registerAttributes() : void{
		$factory = AttributeFactory::getInstance();

		$factory->register(CustomAttributes::ATTACK_KNOCKBACK, 0.00, 340282346638528859811704183484516925440.00, 0.4, false);
	}

	private function registerEntities() : void{
		$factory = EntityFactory::getInstance();

		$factory->register(Endermite::class, function(World $world, CompoundTag $nbt) : Endermite{
			return new Endermite(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:endermite', 'Endermite'], LegacyIds::ENDERMITE);

		$factory->register(Cow::class, function(World $world, CompoundTag $nbt) : Cow{
			return new Cow(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:cow', 'Cow'], LegacyIds::COW);

		$factory->register(MooshroomCow::class, function(World $world, CompoundTag $nbt) : MooshroomCow{
			return new MooshroomCow(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:mooshroom', 'Mooshroom'], LegacyIds::MOOSHROOM);

		$factory->register(Sheep::class, function(World $world, CompoundTag $nbt) : Sheep{
			return new Sheep(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:sheep', 'Sheep'], LegacyIds::SHEEP);
	}

	private function registerSpawnEggs() : void{
		$factory = ItemFactory::getInstance();

		$factory->register(new class(new IID(Ids::SPAWN_EGG, LegacyIds::ENDERMITE), "Endermite Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return new Endermite(Location::fromObject($pos, $world, $yaw, $pitch));
			}
		});

		$factory->register(new class(new IID(Ids::SPAWN_EGG, LegacyIds::MOOSHROOM), "Mooshroom Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return new MooshroomCow(Location::fromObject($pos, $world, $yaw, $pitch));
			}
		});

		$factory->register(new class(new IID(Ids::SPAWN_EGG, LegacyIds::COW), "Cow Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return new Cow(Location::fromObject($pos, $world, $yaw, $pitch));
			}
		});

		$factory->register(new class(new IID(Ids::SPAWN_EGG, LegacyIds::SHEEP), "Sheep Spawn Egg") extends SpawnEgg{
			protected function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity{
				return new Sheep(Location::fromObject($pos, $world, $yaw, $pitch));
			}
		});
	}
}
