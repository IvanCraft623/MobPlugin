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

use IvanCraft623\MobPlugin\entity\monster\slime\SlimeType;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class MagmaCube extends Slime {

	public static function getNetworkTypeId() : string{ return EntityIds::MAGMA_CUBE; }

	public function getName() : string{
		return "Magma Cube";
	}

	public function isFireProof() : bool{
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source->getCause() === EntityDamageEvent::CAUSE_FALL){
			$source->cancel();
		}

		parent::attack($source);
	}

	public function getDefaultMovementSpeed() : float{
		return match($this->type){
			SlimeType::SMALL => 0.6,
			SlimeType::MEDIUM => 0.66,
			SlimeType::LARGE => 0.75
		};
	}

	protected function getTypeAttackDamage() : float{
		return match($this->type){
			SlimeType::SMALL => 3,
			SlimeType::MEDIUM => 4,
			SlimeType::LARGE => 6
		};
	}

	protected function createSplit() : Slime{
		return new MagmaCube($this->getLocation());
	}

	public function getDrops() : array{
		return $this->type === SlimeType::SMALL ? [] : $this->generateDeathLoot(VanillaLootTables::MAGMA_CUBE());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::MAGMA_CUBE_SPAWN_EGG();
	}
}
