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

namespace IvanCraft623\MobPlugin\entity\monster\skeleton;

use Closure;

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\projectile\EffectArrow;
use IvanCraft623\MobPlugin\entity\Shearable;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\sound\ShearSound;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;

class Bogged extends AbstractSkeleton implements Shearable {

	protected const MIN_ATTACK_INTERVAL = 70; // in ticks
	protected const MAX_ATTACK_INTERVAL = 70; // in ticks

	private const POISON_TICKS = 4 * 20;
	private const POISON_ARROW_AUX_VALUE = 26;

	private const COMPONENT_GROUP_SHEARED = "minecraft:bogged_sheared";

	private bool $sheared = false;

	public static function getNetworkTypeId() : string {
		return EntityIds::BOGGED;
	}

	public function getName() : string {
		return "Bogged";
	}

	public function isSunSensitive() : bool{
		return true;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo {
		return new EntitySizeInfo(
			VanillaEntitySizes::BOGGED_HEIGHT,
			VanillaEntitySizes::BOGGED_WIDTH,
			VanillaEntitySizes::BOGGED_HEIGHT * 0.9
		);
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(16);
		$this->setAttackDamage(3);
	}

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);

		$this->sheared = $this->componentGroups->has(self::COMPONENT_GROUP_SHEARED);
	}

	public function saveNBT() : CompoundTag {
		if ($this->sheared) {
			$this->componentGroups->add(self::COMPONENT_GROUP_SHEARED);
		}

		return parent::saveNBT();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void {
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
	}

	protected function createArrow(Location $location) : Arrow {
		return new EffectArrow(
			$location,
			$this,
			false,
			[new EffectInstance(VanillaEffects::POISON(), self::POISON_TICKS)],
			self::POISON_ARROW_AUX_VALUE
		);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool {
		$item = $player->getInventory()->getItemInHand();
		if ($item->getTypeId() === ItemTypeIds::SHEARS && $this->isReadyForShearing()) {
			$this->shear($player, $item);
			Utils::damageItemInHand($player);

			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	public function shear(?Entity $shearer, ?Item $shears) : void {
		$this->broadcastSound(new ShearSound());
		$this->setSheared();

		$this->dropLoot(VanillaLootTables::BOGGED_SHEAR(), $shearer, $shears);
	}

	public function setSheared(bool $sheared = true) : void {
		$this->sheared = $sheared;

		$this->networkPropertiesDirty = true;
	}

	public function isSheared() : bool {
		return $this->sheared;
	}

	public function isReadyForShearing() : bool {
		return $this->isAlive() && !$this->isSheared();
	}

	public function generateEquipment() : void{
		$this->equipFromLootTable(VanillaLootTables::SKELETON_GEAR());
	}

	public function getDrops() : array{
		return [...$this->getEquipmentDrops(), ...$this->generateDeathLoot(VanillaLootTables::BOGGED())];
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::BOGGED_SPAWN_EGG();
	}

	/**
	 * @phpstan-return array<int, int|Closure(Item): int>
	 */
	public static function getWantedItems() : array {
		return Skeleton::getWantedItems();
	}
}
