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

use IvanCraft623\MobPlugin\entity\ambient\Bat;
use IvanCraft623\MobPlugin\entity\animal\Chicken;
use IvanCraft623\MobPlugin\entity\animal\Cow;
use IvanCraft623\MobPlugin\entity\animal\MooshroomCow;
use IvanCraft623\MobPlugin\entity\animal\Pig;
use IvanCraft623\MobPlugin\entity\animal\Sheep;
use IvanCraft623\MobPlugin\entity\CustomAttributes;
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\entity\monster\Enderman;
use IvanCraft623\MobPlugin\entity\monster\Endermite;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\item\ExtraItemRegisterHelper;

use pocketmine\entity\AttributeFactory;
use pocketmine\entity\EntityDataHelper as Helper;
use pocketmine\entity\EntityFactory;
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
		CustomTimings::init();

		$this->registerAttributes();
		$this->registerEntities();

		ExtraItemRegisterHelper::init();
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
		}, ['minecraft:endermite', 'Endermite']);

		$factory->register(Cow::class, function(World $world, CompoundTag $nbt) : Cow{
			return new Cow(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:cow', 'Cow']);

		$factory->register(MooshroomCow::class, function(World $world, CompoundTag $nbt) : MooshroomCow{
			return new MooshroomCow(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:mooshroom', 'Mooshroom']);

		$factory->register(Sheep::class, function(World $world, CompoundTag $nbt) : Sheep{
			return new Sheep(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:sheep', 'Sheep']);

		$factory->register(Creeper::class, function(World $world, CompoundTag $nbt) : Creeper{
			return new Creeper(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:creeper', 'Creeper']);

		$factory->register(Chicken::class, function(World $world, CompoundTag $nbt) : Chicken{
			return new Chicken(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:chicken', 'Chicken']);

		$factory->register(Pig::class, function(World $world, CompoundTag $nbt) : Pig{
			return new Pig(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:pig', 'Pig']);

		$factory->register(Bat::class, function(World $world, CompoundTag $nbt) : Bat{
			return new Bat(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:bat', 'Bat']);

		$factory->register(Slime::class, function(World $world, CompoundTag $nbt) : Slime{
			return new Slime(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:slime', 'Slime']);

		$factory->register(Enderman::class, function(World $world, CompoundTag $nbt) : Enderman{
			return new Enderman(Helper::parseLocation($nbt, $world), $nbt);
		}, ['minecraft:enderman', 'Enderman']);
	}
}
