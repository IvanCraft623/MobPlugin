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
use IvanCraft623\MobPlugin\loot\LootTable;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\MeleeWeaponEnchantment;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\utils\Utils as PMUtils;
use pocketmine\world\sound\ItemBreakSound;
use function assert;
use function max;

/**
 * For mobs that carry an item in their hand: attacking with it, dropping it on death and picking it up.
 *
 * @phpstan-require-extends Mob
 * @phpstan-require-implements ItemCarrier
 */
trait CarriedItemTrait {

	protected MobInventory $inventory;

	protected function initEntity(CompoundTag $nbt) : void{
		$inventory = $this->getInventory();
		if($nbt->count() !== 0){ //The starting gear of a new mob is already in, see onCreate()
			EquipmentNbt::populate($inventory, [
				MobInventory::SLOT_MAIN_HAND => EquipmentNbt::loadSlot($nbt, EquipmentNbt::TAG_MAINHAND)
			]);
		}

		$inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(function() : void{
			foreach($this->getViewers() as $viewer){
				$this->sendHeldItemsPacket($viewer);
			}
		}));
	}

	public function saveNBT() : CompoundTag{
		return CompoundTag::create()
			->setTag(EquipmentNbt::TAG_MAINHAND, EquipmentNbt::saveSlot($this->inventory->getMainHand()));
	}

	protected function sendSpawnPacket(Player $player) : void{
		if (!$this->inventory->getMainHand()->isNull()) {
			$this->sendHeldItemsPacket($player);
		}
	}

	protected function sendHeldItemsPacket(Player $player) : void{
		$networkSession = $player->getNetworkSession();
		$converter = $networkSession->getTypeConverter();
		$networkSession->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($converter->coreItemStackToNet($this->inventory->getMainHand())),
			$this->inventory->getHeldItemIndex(),
			$this->inventory->getHeldItemIndex(),
			ContainerIds::INVENTORY
		));
	}

	/**
	 * Created when first needed: a new mob is equipped by onCreate(), before the host loads it.
	 */
	public function getInventory() : MobInventory{
		return $this->inventory ??= new MobInventory($this, 1);
	}

	/**
	 * Attacks the given entity with the item in hand.
	 *
	 * @return bool if the entity was dealt damage
	 */
	public function attackEntity(Entity $entity) : bool{
		if(!$entity->isAlive()){
			return false;
		}

		$inventory = $this->getInventory();
		$heldItem = $inventory->getItemInHand();
		$oldItem = clone $heldItem;

		$itemAttackPoints = $heldItem->getAttackPoints();
		$attackPoints = $this->getAttackDamage();
		$baseDamage = $itemAttackPoints <= 1 ? $attackPoints : $attackPoints + $itemAttackPoints;

		$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $baseDamage);
		$ev->setKnockBack($this->getAttackKnockback());

		$meleeEnchantmentDamage = 0;
		/** @var EnchantmentInstance[] $meleeEnchantments */
		$meleeEnchantments = [];
		foreach($heldItem->getEnchantments() as $enchantment){
			$type = $enchantment->getType();
			if($type instanceof MeleeWeaponEnchantment && $type->isApplicableTo($entity)){
				$meleeEnchantmentDamage += $type->getDamageBonus($enchantment->getLevel());
				$meleeEnchantments[] = $enchantment;
			}
		}
		$ev->setModifier($meleeEnchantmentDamage, EntityDamageEvent::MODIFIER_WEAPON_ENCHANTMENTS);

		$entity->attack($ev);

		$this->doAttackAnimation();

		if ($this->isOnFire()) {
			$entity->setOnFire(8);
		}

		foreach($meleeEnchantments as $enchantment){
			$type = $enchantment->getType();
			assert($type instanceof MeleeWeaponEnchantment);
			$type->onPostAttack($this, $entity, $enchantment->getLevel());
		}

		if($this->isAlive()){
			//reactive damage like thorns might cause us to be killed by attacking another mob, which
			//would mean we'd already have dropped the inventory by the time we reached here

			$returnedItems = []; //TODO: do something with returned items
			if($heldItem->onAttackEntity($entity, $returnedItems) && $oldItem->equalsExact($inventory->getItemInHand())){
				if($heldItem instanceof Durable && $heldItem->isBroken()){
					$this->broadcastSound(new ItemBreakSound());
				}
				$inventory->setItemInHand($heldItem);
			}
		}

		return true;
	}

	/**
	 * Chance that the item in this slot drops on death.
	 */
	public function getEquipmentDropProbability(Item $item, Inventory $inventory, int $slot) : float{
		return 0.25;
	}

	/**
	 * What drops from the item in hand, each at its own chance.
	 *
	 * @return Item[]
	 */
	public function getCarriedItemDrops() : array{
		return $this->rollEquipmentDrops($this->getInventory());
	}

	/**
	 * @return Item[]
	 */
	protected function rollEquipmentDrops(Inventory $inventory) : array{
		$drops = [];

		foreach ($inventory->getContents() as $slot => $item) {
			if (!$item->hasEnchantment(VanillaEnchantments::VANISHING()) &&
				PMUtils::getRandomFloat() <= $this->getEquipmentDropProbability($item, $inventory, $slot)
			) {
				if ($item instanceof Durable) {
					$maxDurability = $item->getMaxDurability();
					$item->setDamage(
						$maxDurability - $this->random->nextBoundedInt(1 + $this->random->nextBoundedInt(max($maxDurability - 3, 1)))
					);
				}
				$drops[] = $item;
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
		$slot = MobInventory::SLOT_MAIN_HAND;
		return $this->getInventory();
	}

	/**
	 * @return Item[]
	 */
	abstract protected function generateLoot(LootTable $table, ?Entity $source = null, ?Item $tool = null) : array;
}
