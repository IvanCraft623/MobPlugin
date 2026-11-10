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

use IvanCraft623\MobPlugin\inventory\MobInventory;
use IvanCraft623\MobPlugin\loot\LootTable;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Armor;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\utils\Utils as PMUtils;
use function max;

/**
 * For mobs that wear or hold items: rolling gear, dropping it on death and picking the slot an item goes in.
 */
trait EquipmentTrait {

	/**
	 * Chance that the item in this slot drops on death.
	 */
	public function getEquipmentDropProbability(Item $item, Inventory $inventory, int $slot) : float{
		return 0.25;
	}

	/**
	 * @return Item[]
	 */
	public function getEquipmentDrops() : array{
		$drops = [];

		foreach ([$this->inventory, $this->armorInventory] as $inventory) {
			foreach ($inventory->getContents() as $slot => $item) {
				if (!$item->hasEnchantment(VanillaEnchantments::VANISHING()) &&
					PMUtils::getRandomFloat() <= $this->getEquipmentDropProbability($item, $inventory, $slot)
				) {
					if ($item instanceof Durable) {
						$maxDurability = $item->getMaxDurability();
						$item->setDamage($maxDurability - $this->random->nextBoundedInt(1 + $this->random->nextBoundedInt(max($maxDurability - 3, 1))));
					}
					$drops[] = $item;
				}
			}
		}

		return $drops;
	}

	/**
	 * Rolls a table of gear for this mob and puts each item in its slot.
	 */
	protected function equipFromLootTable(LootTable $table) : void{
		foreach ($this->generateLoot($table) as $item) {
			$slot = 0;
			$this->getEquipInventoryAndSlot($item, $slot)->setItem($slot, $item);
		}
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

		$slot = MobInventory::SLOT_MAIN_HAND;
		return $this->getInventory();
	}

	/**
	 * @return Item[]
	 */
	abstract protected function generateLoot(LootTable $table, ?Entity $source = null, ?Item $tool = null) : array;
}
