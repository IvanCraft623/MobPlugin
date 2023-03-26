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

use IvanCraft623\MobPlugin\ai\Brain;
use IvanCraft623\MobPlugin\inventory\MobInventory;
use IvanCraft623\MobPlugin\MobPlugin;

use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\Living as PMLiving;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\utils\Random;
use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_values;

abstract class Living extends PMLiving {
	//TODO!

	protected Random $random;

	protected float $defaultSpeed = 1.0;

	protected float $speed;

	protected float $maxUpStep = 0.6;

	protected MobInventory $inventory;

	protected Brain $brain;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->random = MobPlugin::getInstance()->getRandom();
		$this->speed = $this->defaultSpeed;

		$this->inventory = new MobInventory($this);
		$syncHeldItem = function() : void{
			$inv = $this->getInventory();
			$packet = MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($inv->getItemInHand())), $inv->getHeldItemIndex(), $inv->getHeldItemIndex(), ContainerIds::INVENTORY);
			foreach($this->getViewers() as $viewer){
				$viewer->getNetworkSession()->sendDataPacket($packet);
			}
		};
		$this->inventory->getListeners()->add(new CallbackInventoryListener(
			function(Inventory $unused, int $slot, Item $unused2) use ($syncHeldItem) : void{
				if($slot === $this->inventory->getHeldItemIndex()){
					$syncHeldItem();
				}
			},
			function(Inventory $unused, array $oldItems) use ($syncHeldItem) : void{
				if(array_key_exists($this->inventory->getHeldItemIndex(), $oldItems)){
					$syncHeldItem();
				}
			}
		));
		$inventoryTag = $nbt->getListTag("Inventory");
		if($inventoryTag !== null){
			$inventoryItems = [];
			$armorInventoryItems = [];

			/** @var CompoundTag $item */
			foreach($inventoryTag as $i => $item){
				$slot = $item->getByte("Slot");
				if($slot >= 0 && $slot < $this->inventory->getSize()){ //Inventory
					$inventoryItems[$slot] = Item::nbtDeserialize($item);
				}elseif($slot >= 100 && $slot < 104){ //Armor
					$armorInventoryItems[$slot - 100] = Item::nbtDeserialize($item);
				}
			}

			self::populateInventoryFromListTag($this->inventory, $inventoryItems);
			self::populateInventoryFromListTag($this->armorInventory, $armorInventoryItems);
		}

		$this->brain = $this->makeBrain();
	}

	protected function makeBrain() : Brain{
		return new Brain([], [], []);
	}

	/**
	 * @param Item[] $items
	 * @phpstan-param array<int, Item> $items
	 */
	private static function populateInventoryFromListTag(Inventory $inventory, array $items) : void{
		$listeners = $inventory->getListeners()->toArray();
		$inventory->getListeners()->clear();

		$inventory->setContents($items);

		$inventory->getListeners()->add(...$listeners);
	}

	public function getRandom() : Random {
		return $this->random;
	}

	public function getDefaultSpeed() : float {
		return $this->defaultSpeed;
	}

	public function getSpeed() : float {
		return $this->speed;
	}

	public function setSpeed(float $speed) : void {
		$this->speed = $speed;
	}

	public function getMaxUpStep() : float {
		return $this->maxUpStep;
	}

	public function getInventory() : MobInventory{
		return $this->inventory;
	}

	public function getBrain() : Brain{
		return $this->brain;
	}

	public function canAttack(PMLiving $target) : bool {
		return true;
	}

	public function canSee(Entity $entity) : bool{
		$start = $this->getEyePos();
		$end = $entity->getEyePos();
		$directionVector = $end->subtractVector($start)->normalize();
		if ($directionVector->lengthSquared() > 0) {
			foreach(VoxelRayTrace::betweenPoints($start, $end) as $vector3){
				$block = $this->getWorld()->getBlockAt((int) $vector3->x, (int) $vector3->y, (int) $vector3->z);

				$blockHitResult = $block->calculateIntercept($start, $end);
				if(!$block->isTransparent() && $blockHitResult !== null){
					return false;
				}
			}
		}
		return true;
	}

	public function isTouchingWater() : bool{
		foreach ($this->getWorld()->getCollisionBlocks($this->getBoundingBox()) as $block) {
			if ($block instanceof Water) {
				//TODO: waterlogging check and do not trigger with powder snow
				return true;
			}
		}

		return false;
	}

	public function canStandOnFluid(Liquid $liquid) : bool{
		return false;
	}

	public function getMaxFallDistance() : int{
		return 3;
	}

	public function getDrops() : array {
		return array_filter(array_merge(
			array_values($this->inventory->getContents()),
			$this->armorInventory !== null ? array_values($this->armorInventory->getContents()) : []
		), function(Item $item) : bool{ return !$item->hasEnchantment(VanillaEnchantments::VANISHING()); });
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT();

		$inventoryTag = new ListTag([], NBT::TAG_Compound);
		$nbt->setTag("Inventory", $inventoryTag);
		if($this->inventory !== null){
			//Normal inventory
			for($slot = 0; $slot < $this->inventory->getSize(); ++$slot){
				$item = $this->inventory->getItem($slot);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($slot));
				}
			}

			//Armor
			for($slot = 100; $slot < 104; ++$slot){
				$item = $this->armorInventory->getItem($slot - 100);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($slot));
				}
			}

			return $nbt;
		}
	}
}
