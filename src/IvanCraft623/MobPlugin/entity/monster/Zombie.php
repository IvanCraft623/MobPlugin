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
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Zombie extends AbstractZombie implements DoorBreaker {
	use DoorBreakerTrait;

	public static function getNetworkTypeId() : string {
		return EntityIds::ZOMBIE;
	}

	public function getName() : string {
		return "Zombie";
	}

	public function isSunSensitive() : bool{
		return true;
	}

	protected function getWaterConversionType() : ?string {
		//TODO: Drowned!
		return null;
	}

	public function generateEquipment() : void{
		$this->equipFromLootTable(VanillaLootTables::ZOMBIE_EQUIPMENT());
	}

	public function getDrops() : array {
		$drops = [...$this->getEquipmentDrops(), ...$this->generateDeathLoot(VanillaLootTables::ZOMBIE())];

		if (($cause = $this->getLastDamageCause()) instanceof EntityDamageByEntityEvent &&
			($killer = $cause->getDamager()) instanceof Creeper && $killer->isPowered()
		) {
			$drops[] = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::ZOMBIE)->asItem();
		}

		return $drops;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOMBIE_SPAWN_EGG();
	}
}
