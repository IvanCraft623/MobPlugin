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

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\ai\goal\AvoidSunlightGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FleeSunlightGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PickupItemsGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RangedBowAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\EquipmentTrait;
use IvanCraft623\MobPlugin\entity\Equipped;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\ItemPickupCapable;
use IvanCraft623\MobPlugin\entity\ItemPickupCapableTrait;
use IvanCraft623\MobPlugin\entity\MobType;
use IvanCraft623\MobPlugin\entity\monster\Monster;
use IvanCraft623\MobPlugin\entity\RangedAttackMob;
use IvanCraft623\MobPlugin\inventory\MobInventory;
use IvanCraft623\MobPlugin\utils\Utils;
use IvanCraft623\Pathfinder\BlockPathType;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\sound\BowShootSound;
use function count;
use function mt_rand;

abstract class AbstractSkeleton extends Monster implements Equipped, RangedAttackMob, ItemPickupCapable {
	use EquipmentTrait, ItemPickupCapableTrait {
		//initEntity()
		EquipmentTrait::initEntity insteadof ItemPickupCapableTrait;
		EquipmentTrait::initEntity as protected initEquipment;
		ItemPickupCapableTrait::initEntity as protected initPickup;

		//saveNBT()
		EquipmentTrait::saveNBT insteadof ItemPickupCapableTrait;
		EquipmentTrait::saveNBT as protected saveEquipment;
		ItemPickupCapableTrait::saveNBT as protected savePickup;

		//sendSpawnPacket()
		EquipmentTrait::sendSpawnPacket as protected sendEquipmentSpawnPacket;

		//attackEntity()
		EquipmentTrait::attackEntity as protected attackEntityWithHeldItem;
	}

	protected const MIN_ATTACK_INTERVAL = 20; // in ticks
	protected const MAX_ATTACK_INTERVAL = 60; // in ticks

	protected HurtByTargetGoal $hurtByTargetGoal;

	protected RangedBowAttackGoal $bowAttackGoal;
	protected MeleeAttackGoal $meleeAttackGoal;
	private bool $wasUnderwater = false;

	protected PickupItemsGoal $pickupItemsGoal;

	public function getMobType() : MobType{
		return MobType::UNDEAD();
	}

	protected function getInitialSizeInfo() : EntitySizeInfo {
		return new EntitySizeInfo(
			VanillaEntitySizes::SKELETON_HEIGHT,
			VanillaEntitySizes::SKELETON_WIDTH,
			VanillaEntitySizes::SKELETON_HEIGHT * 0.9
		);
	}

	protected function registerGoals() : void{
		$this->pickupItemsGoal = (new PickupItemsGoal($this, 1, 2, 4))->setWantedItems(static::getWantedItems());

		$this->goalSelector->addGoal(2, new AvoidSunlightGoal($this));
		$this->goalSelector->addGoal(3, new FleeSunlightGoal($this, 1));
		//TODO: 3 flee wolfs
		$this->goalSelector->addGoal(5, new RandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(6, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, $this->hurtByTargetGoal = new HurtByTargetGoal($this));
		$this->targetSelector->addGoal(2, new NearestAttackableGoal(entity: $this, targetType: Player::class, mustSee: true));
		$this->targetSelector->addGoal(3, new NearestAttackableGoal(entity: $this, targetType: IronGolem::class, mustSee: true));
		//TODO: attack baby turtles

		$this->bowAttackGoal = new RangedBowAttackGoal($this, 1, static::MIN_ATTACK_INTERVAL, static::MAX_ATTACK_INTERVAL, 15);
		$this->meleeAttackGoal = new MeleeAttackGoal($this, 1.25);
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->updateAttackType();
		$this->updatePickupItemsGoal($this->canPickupItems());
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$this->initEquipment($nbt);
		$this->getInventory()->getListeners()->add(new CallbackInventoryListener(function(Inventory $inventory, int $slot, Item $oldItem) : void {
			if ($slot !== MobInventory::SLOT_MAIN_HAND) {
				return;
			}

			$this->updateAttackType();
		}, null));
		$this->initPickup($nbt);

		if ($nbt->count() === 0) { // First spawn
			// Halloween pumpkin head chance, this is a java only feature, but why not?
			if ($this->getArmorInventory()->getHelmet()->isNull() && Utils::isHalloween() && mt_rand(1, 4) === 1) {
				$this->getArmorInventory()->setHelmet(
					mt_rand(1, 10) === 1 ?
					VanillaBlocks::LIT_PUMPKIN()->asItem() :
					VanillaBlocks::CARVED_PUMPKIN()->asItem()
				);
			}
		}
	}

	public function attackEntity(Entity $entity) : bool {
		return $this->attackEntityWithHeldItem($entity);
	}

	protected function sendSpawnPacket(Player $player) : void {
		parent::sendSpawnPacket($player);
		$this->sendEquipmentSpawnPacket($player);
	}

	public function saveNBT() : CompoundTag {
		return parent::saveNBT()->merge($this->saveEquipment())->merge($this->savePickup());
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(20);
		$this->setAttackDamage(2);
		$this->navigation->setPathfindingMalus(BlockPathType::WATER, 0);
	}

	protected function updatePickupItemsGoal(bool $enabled) : void{
		if ($enabled) {
			$this->goalSelector->addGoal(4, $this->pickupItemsGoal);
		} else {
			$this->goalSelector->removeGoal($this->pickupItemsGoal);
		}
	}

	protected function tickState() : void{
		parent::tickState();

		$underwater = $this->getSensing()->isUnderwater();
		if ($underwater !== $this->wasUnderwater) {
			$this->wasUnderwater = $underwater;
			$this->updateAttackType();
		}
	}

	public function updateAttackType() : void {
		$useMeleeAttackGoal = !$this->canUseBow();

		$this->goalSelector->removeGoal($useMeleeAttackGoal ? $this->bowAttackGoal : $this->meleeAttackGoal);
		$this->goalSelector->addGoal(0, $useMeleeAttackGoal ? $this->meleeAttackGoal : $this->bowAttackGoal);
	}

	protected function canUseBow() : bool {
		return !$this->getSensing()->isUnderwater() && $this->getInventory()->getMainHand()->getTypeId() === ItemTypeIds::BOW;
	}

	protected function createArrow(Location $location) : Arrow {
		return new Arrow($location, $this, false);
	}

	public function performRangedAttack(Entity $target, float $force) : void {
		$eyePos = $this->getEyePos();
		$world = $this->getWorld();

		$projectile = $this->createArrow(Location::fromObject($eyePos, $world));
		$projectile->setPickupMode(Arrow::PICKUP_NONE);

		$projectile->setMotion(Utils::aimProjectile(
			$this->getRandom(),
			$eyePos,
			$target->getPosition()->add(0, $target->getSize()->getHeight() / 3, 0),
			1.6 * $force,
			14 - $world->getDifficulty() * 4
		));
		$projectile->spawnToAll();

		$world->addSound($eyePos, new BowShootSound());
	}

	public function getXpDropAmount() : int{
		if ($this->hasBeenDamagedByPlayer()) {
			return 5 + (count($this->getArmorInventory()->getContents()) * mt_rand(1, 3));
		}

		return 0;
	}

	public function getHurtByTargetGoal() : HurtByTargetGoal{
		return $this->hurtByTargetGoal;
	}

	protected function destroyCycles() : void{
		unset(
			$this->hurtByTargetGoal,
			$this->bowAttackGoal,
			$this->meleeAttackGoal,
			$this->pickupItemsGoal
		);
		parent::destroyCycles();
	}
}
