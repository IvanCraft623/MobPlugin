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

namespace IvanCraft623\MobPlugin\entity\monster;

use IvanCraft623\MobPlugin\entity\DoorBreaker;
use IvanCraft623\MobPlugin\entity\DoorBreakerTrait;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living as PMLiving;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Husk extends AbstractZombie implements DoorBreaker {
	use DoorBreakerTrait;

	private const HUNGER_TICKS = 30 * 20;

	public static function getNetworkTypeId() : string {
		return EntityIds::HUSK;
	}

	public function getName() : string {
		return "Husk";
	}

	protected function getWaterConversionType() : ?string {
		return Zombie::class;
	}

	public function generateEquipment() : void{
		$this->equipFromLootTable(VanillaLootTables::ZOMBIE_EQUIPMENT());
	}

	public function attackEntity(Entity $entity) : bool {
		$dealt = parent::attackEntity($entity);

		if ($dealt && $entity instanceof PMLiving && $this->getInventory()->getMainHand()->isNull()) {
			$entity->getEffects()->add(new EffectInstance(VanillaEffects::HUNGER(), self::HUNGER_TICKS));
		}

		return $dealt;
	}

	public function getDrops() : array {
		return [...$this->getEquipmentDrops(), ...$this->generateDeathLoot(VanillaLootTables::ZOMBIE())];
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::HUSK_SPAWN_EGG();
	}
}
