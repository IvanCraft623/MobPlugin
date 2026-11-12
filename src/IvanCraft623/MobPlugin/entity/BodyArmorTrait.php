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

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\inventory\EquipmentNbt;
use IvanCraft623\MobPlugin\inventory\SingleSlotInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;

/**
 * Keeps the body armor in a slot of its own and shows it to the viewers. Not for mobs that wear other armor.
 *
 * @phpstan-require-extends Mob
 * @phpstan-require-implements BodyArmored
 */
trait BodyArmorTrait{

	protected SingleSlotInventory $bodyArmorInventory;

	protected function initEntity(CompoundTag $nbt) : void{
		$this->bodyArmorInventory = new SingleSlotInventory($this);
		EquipmentNbt::populate($this->bodyArmorInventory, [EquipmentNbt::loadBody($nbt)]);

		$this->bodyArmorInventory->getListeners()->add(CallbackInventoryListener::onAnyChange(function() : void{
			foreach($this->getViewers() as $viewer){
				$this->sendBodyArmorPacket($viewer);
			}
		}));
	}

	public function saveNBT() : CompoundTag{
		return CompoundTag::create()
			->setTag(EquipmentNbt::TAG_ARMOR, EquipmentNbt::saveBodyArmor($this->bodyArmorInventory->get()));
	}

	protected function sendSpawnPacket(Player $player) : void{
		if (!$this->bodyArmorInventory->get()->isNull()) {
			$this->sendBodyArmorPacket($player);
		}
	}

	protected function sendBodyArmorPacket(Player $player) : void{
		$networkSession = $player->getNetworkSession();
		$converter = $networkSession->getTypeConverter();
		$empty = ItemStackWrapper::legacy(ItemStack::null());
		$networkSession->sendDataPacket(MobArmorEquipmentPacket::create(
			$this->getId(),
			$empty,
			$empty,
			$empty,
			$empty,
			ItemStackWrapper::legacy($converter->coreItemStackToNet($this->bodyArmorInventory->get()))
		));
	}

	public function getBodyArmor() : Item{
		return $this->bodyArmorInventory->get();
	}

	public function setBodyArmor(Item $armor) : void{
		$this->bodyArmorInventory->set($armor);
	}
}
