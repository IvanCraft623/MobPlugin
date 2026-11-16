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
use IvanCraft623\MobPlugin\inventory\MobInventory;
use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;

/**
 * For mobs that wear armor and hold items in both hands, on top of {@see CarriedItemTrait}.
 *
 * @phpstan-require-extends Mob
 * @phpstan-require-implements Equipped
 */
trait EquipmentTrait {
	use CarriedItemTrait {
		initEntity as protected initCarriedItem;
		saveNBT as protected saveCarriedItem;
		getEquipInventoryAndSlot as protected getCarriedInventoryAndSlot;
		sendHeldItemsPacket as protected sendCarriedItemPacket;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->initCarriedItem($nbt);

		if($nbt->count() !== 0){ //The starting gear of a new mob is already in, see onCreate()
			EquipmentNbt::populate($this->getInventory(), [
				MobInventory::SLOT_OFFHAND => EquipmentNbt::loadSlot($nbt, EquipmentNbt::TAG_OFFHAND)
			]);
			EquipmentNbt::loadArmor($nbt, $this->armorInventory);
		}
	}

	public function saveNBT() : CompoundTag{
		return $this->saveCarriedItem()
			->setTag(EquipmentNbt::TAG_OFFHAND, EquipmentNbt::saveSlot($this->inventory->getOffHand()))
			->setTag(EquipmentNbt::TAG_ARMOR, EquipmentNbt::saveArmor($this->armorInventory, VanillaItems::AIR()));
	}

	/**
	 * Created when first needed: a new mob is equipped by onCreate(), before the host loads it.
	 */
	public function getInventory() : MobInventory{
		return $this->inventory ??= new MobInventory($this);
	}

	protected function sendSpawnPacket(Player $player) : void{
		if (!$this->inventory->getMainHand()->isNull() || !$this->inventory->getOffHand()->isNull()) {
			$this->sendHeldItemsPacket($player);
		}
	}

	protected function sendHeldItemsPacket(Player $player) : void{
		$this->sendCarriedItemPacket($player);

		$networkSession = $player->getNetworkSession();
		$networkSession->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($networkSession->getTypeConverter()->coreItemStackToNet($this->inventory->getOffHand())),
			0,
			0,
			ContainerIds::OFFHAND
		));
	}

	/**
	 * @return Item[]
	 */
	public function getEquipmentDrops() : array{
		return [...$this->getCarriedItemDrops(), ...$this->rollEquipmentDrops($this->armorInventory)];
	}

	public function getEquipInventoryAndSlot(Item $item, int &$slot) : Inventory{
		if ($item instanceof Armor) {
			$slot = $item->getArmorSlot();
			return $this->getArmorInventory();
		}

		$typeId = $item->getTypeId();
		if ($typeId === ItemTypeIds::fromBlockTypeId(BlockTypeIds::CARVED_PUMPKIN) ||
			$typeId === ItemTypeIds::fromBlockTypeId(BlockTypeIds::MOB_HEAD)
		) {
			$slot = ArmorInventory::SLOT_HEAD;
			return $this->getArmorInventory();
		}

		//TODO: other items like elytra may be equiped on an armor slot
		//TODO: it could be items equiped on offhand

		return $this->getCarriedInventoryAndSlot($item, $slot);
	}
}