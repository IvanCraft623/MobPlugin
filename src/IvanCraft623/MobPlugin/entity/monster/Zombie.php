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

use Closure;

use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\BreakDoorGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\DestroyEggGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PickupItemsGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\navigation\GroundPathNavigation;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\ItemPickupCapable;
use IvanCraft623\MobPlugin\entity\ItemPickupCapableTrait;
use IvanCraft623\MobPlugin\sound\SoundEvents;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Ageable;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\World;

use function assert;
use function count;
use function mt_rand;

class Zombie extends Monster implements Ageable, ItemPickupCapable {
	use ItemPickupCapableTrait {
		initEntity as initEntityPickupTrait;
		saveNBT as saveNBTPickupTrait;
	}

	private const TAG_IS_BABY = "IsBaby"; //TAG_Byte

	private const COMPONENT_GROUP_CAN_BREAK_DOORS = "minecraft:can_break_doors";

	private const WATER_CONVERSION_TIME = 300;
	private const MAX_WATER_TIME_BEFORE_CONVERSION = 600;

	private bool $isBaby = false;

	private bool $canBreakDoors = false;

	private int $inWaterTime = 0;

	private int $ticksUntilWaterConversion = -1;

	private bool $isConvertingInWater = false;

	private BreakDoorGoal $breakDoorGoal;

	private PickupItemsGoal $pickupItemsGoal;

	public static function getNetworkTypeId() : string {
		return EntityIds::ZOMBIE;
	}

	public function getName() : string {
		return "Zombie";
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6, 1.71);
	}

	protected function initProperties() : void {
		parent::initProperties();

		$this->setMaxHealth(20);
		$this->setAttackDamage(3);
		$this->setFollowRange(35);
	}

	protected function registerGoals() : void {
		$this->breakDoorGoal = new BreakDoorGoal($this, World::DIFFICULTY_HARD);
		$this->pickupItemsGoal = (new PickupItemsGoal($this, 1, 2, 4))->setWantedItems(self::getWantedItems());

		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(2, new MeleeAttackGoal($this, 1, false));
		$this->goalSelector->addGoal(7, new WaterAvoidingRandomStrollGoal($this, 1));
		//$this->goalSelector->addGoal(4, new DestroyEggGoal($this, 1.0, 3)); //TODO!
		$this->goalSelector->addGoal(8, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(8, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, new HurtByTargetGoal($this));
		$this->targetSelector->addGoal(2, new NearestAttackableGoal(entity: $this, targetType: Player::class, mustSee: true));
		$this->targetSelector->addGoal(3, new NearestAttackableGoal(entity: $this, targetType: Villager::class, mustSee: false));
		$this->targetSelector->addGoal(3, new NearestAttackableGoal(entity: $this, targetType: IronGolem::class, mustSee: true));

		//TODO: Attack baby turtles!
		//$this->targetSelector->addGoal(5, new NearestAttackableGoal($this, Turtle::class, 10, true, false, fn($turtle) => $turtle->isBaby()));
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->updatePickupItemsGoal($this->canPickupItems());
		$this->updateBreakDoorGoal($this->canBreakDoors);
	}

	public function isBaby() : bool{
		return $this->isBaby;
	}

	public function setBaby(bool $value = true) : void{
		if ($value !== $this->isBaby) {
			$this->setScale($value ? $this->getBabyScale() : 1);

			$this->isBaby = $value;
			$this->networkPropertiesDirty = true;
		}
	}

	public function getBabyScale() : float{
		return 0.5;
	}

	protected function updatePickupItemsGoal(bool $enabled) : void{
		if ($enabled) {
			$this->goalSelector->addGoal(6, $this->pickupItemsGoal);
		} else {
			$this->goalSelector->removeGoal($this->pickupItemsGoal);
		}
	}

	public function canBreakDoors() : bool {
		return $this->canBreakDoors;
	}

	/**
	 * @return $this
	 */
	public function setCanBreakDoors(bool $value = true) : self{
		if ($this->canBreakDoors !== $value) {
			$this->canBreakDoors = $value;

			assert($this->navigation instanceof GroundPathNavigation);
			$this->navigation->setCanOpenDoors($value);
			$this->updateBreakDoorGoal($value);
		}
		return $this;
	}

	protected function updateBreakDoorGoal(bool $enabled) : void{
		if ($enabled) {
			$this->goalSelector->addGoal(1, $this->breakDoorGoal);
		} else {
			$this->goalSelector->removeGoal($this->breakDoorGoal);
		}
	}

	public function isConvertingInWater() : bool {
		return $this->isConvertingInWater;
	}

	protected function setTicksUntilWaterConversion(int $ticks) : void{
		$this->ticksUntilWaterConversion = $ticks;
		$this->isConvertingInWater = true;
	}

	protected function canConvertInWater() : bool {
		return true;
	}

	protected function convertInWater() : void {
		//TODO!
		/*$this->convertTo(Drowned::class);
		$this->broadcastSound(SoundEvents::ENTITY_ZOMBIE_CONVERTED_TO_DROWNED());*/
	}

	protected function convertTo(string $entityType) : void {
		//TODO!
		/*$newEntity = new $entityType($this->getLocation());
		$newEntity->setBaby($this->isBaby());
		$newEntity->spawnToAll();

		$this->flagForDespawn();*/
	}

	public function isSunSensitive() : bool{
		return true;
	}

	public function tickAi() : void{
		parent::tickAi();

		if (!$this->isAlive()) {
			return;
		}

		if ($this->isConvertingInWater()) {
			$this->ticksUntilWaterConversion--;
			if ($this->ticksUntilWaterConversion < 0) {
				$this->convertInWater();
			}
		} elseif ($this->canConvertInWater()) {
			if ($this->isUnderwater()) {
				$this->inWaterTime++;
				if ($this->inWaterTime >= self::MAX_WATER_TIME_BEFORE_CONVERSION) {
					$this->setTicksUntilWaterConversion(self::WATER_CONVERSION_TIME);
				}
			} else {
				$this->inWaterTime = -1;
			}
		}
	}

	public function getDrops() : array {
		$drops = parent::getDrops();

		$drops[] = VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 2));

		// Rare iron drop
		//TODO: looting enchantment logic
		if (mt_rand(1, 40) === 1) { // 2.5% chance
			$drops[] = match(mt_rand(0, 2)){
				0 => VanillaItems::IRON_INGOT(),
				1 => VanillaItems::CARROT(),
				2 => VanillaItems::POTATO(),
			};
		}

		if (($cause = $this->getLastDamageCause()) instanceof EntityDamageByEntityEvent &&
			($killer = $cause->getDamager()) instanceof Creeper && $killer->isPowered()
		) {
			$drops[] = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::ZOMBIE)->asItem();
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		if ($this->hasBeenDamagedByPlayer()) {
			return ($this->isBaby() ? 12 : 5) + (count($this->getArmorInventory()->getContents()) * mt_rand(1, 3));
		}

		return 0;
	}

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$this->initEntityPickupTrait($nbt);

		$isBabyByte = $nbt->getByte(self::TAG_IS_BABY, -1);
		if ($isBabyByte === 1 || ($isBabyByte === -1 && AgeableMob::getRandomStartAge() !== AgeableMob::ADULT_AGE)) {
			$this->setBaby();
			//TODO: 0.75% chance of zombie jockey
		}

		if ($nbt->count() !== 0) {
			$this->setCanBreakDoors($this->componentGroups->has(self::COMPONENT_GROUP_CAN_BREAK_DOORS));
		} else { // First spawn
			$this->setCanBreakDoors(mt_rand(1, 10) === 1);

			// Halloween pumpkin head chance, this is a java only feature, but whhy not?
			if ($this->getArmorInventory()->getHelmet()->isNull() && Utils::isHalloween() && mt_rand(1, 4) === 1) {
				$this->getArmorInventory()->setHelmet(
					mt_rand(1, 10) === 1 ?
					VanillaBlocks::LIT_PUMPKIN()->asItem() :
					VanillaBlocks::CARVED_PUMPKIN()->asItem()
				);
			}
		}
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT()->merge($this->saveNBTPickupTrait());

		$nbt->setByte(self::TAG_IS_BABY, $this->isBaby() ? 1 : 0);

		//TODO: this is a hack and we are only saving the minimum for our implementation.
		if ($this->canBreakDoors) {
			$this->componentGroups->add(self::COMPONENT_GROUP_CAN_BREAK_DOORS);
		}

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void {
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	public function onKillEntity(Entity $victim) : void {
		//TODO: implement zombie infection to villagers
		/*parent::onKilled($killer);

		if ($killer instanceof Player && $this->getWorld()->getDifficulty() >= 2 &&
			$this->getLastAttacker() instanceof Villager) {
			// Chance to convert villager to zombie villager
			if ($this->getWorld()->getDifficulty() === 3 || mt_rand(0, 1) === 0) {
				// TODO: Implement zombie villager conversion
			}
		}*/
	}

	public function getDefaultMovementSpeed() : float {
		return $this->isBaby() ? 0.35 : 0.23;
	}

	/**
	 * @phpstan-return array<int, int|Closure(Item): int>
	 */
	public static function getWantedItems() : array {
		$items = [];

		$items[] = ItemTypeIds::NETHERITE_SWORD;
		// TODO: netherite spear
		$items[] = ItemTypeIds::NETHERITE_HELMET;
		$items[] = ItemTypeIds::NETHERITE_CHESTPLATE;
		$items[] = ItemTypeIds::NETHERITE_LEGGINGS;
		$items[] = ItemTypeIds::NETHERITE_BOOTS;

		$items[] = ItemTypeIds::DIAMOND_SWORD;
		// TODO: diamond spear
		$items[] = ItemTypeIds::DIAMOND_HELMET;
		$items[] = ItemTypeIds::DIAMOND_CHESTPLATE;
		$items[] = ItemTypeIds::DIAMOND_LEGGINGS;
		$items[] = ItemTypeIds::DIAMOND_BOOTS;

		$items[] = ItemTypeIds::IRON_SWORD;
		// TODO: iron spear
		$items[] = ItemTypeIds::IRON_HELMET;
		$items[] = ItemTypeIds::IRON_CHESTPLATE;
		$items[] = ItemTypeIds::IRON_LEGGINGS;
		$items[] = ItemTypeIds::IRON_BOOTS;

		$items[] = ItemTypeIds::COPPER_SWORD;
		// TODO: copper spear

		$items[] = ItemTypeIds::CHAINMAIL_HELMET;
		$items[] = ItemTypeIds::CHAINMAIL_CHESTPLATE;
		$items[] = ItemTypeIds::CHAINMAIL_LEGGINGS;
		$items[] = ItemTypeIds::CHAINMAIL_BOOTS;

		$items[] = ItemTypeIds::GOLDEN_SWORD;
		// TODO: golden spear
		$items[] = ItemTypeIds::GOLDEN_HELMET;
		$items[] = ItemTypeIds::GOLDEN_CHESTPLATE;
		$items[] = ItemTypeIds::GOLDEN_LEGGINGS;
		$items[] = ItemTypeIds::GOLDEN_BOOTS;

		$items[] = ItemTypeIds::COPPER_HELMET;
		$items[] = ItemTypeIds::COPPER_CHESTPLATE;
		$items[] = ItemTypeIds::COPPER_LEGGINGS;
		$items[] = ItemTypeIds::COPPER_BOOTS;

		$items[] = ItemTypeIds::STONE_SWORD;
		// TODO: stone spear

		$items[] = ItemTypeIds::WOODEN_SWORD;
		// TODO: wooden spear
		$items[] = ItemTypeIds::LEATHER_CAP;
		$items[] = ItemTypeIds::LEATHER_TUNIC;
		$items[] = ItemTypeIds::LEATHER_PANTS;
		$items[] = ItemTypeIds::LEATHER_BOOTS;

		$items[] = ItemTypeIds::TURTLE_HELMET;

		// skeleton and wither skeleton skulls
		$items[] = static function(Item $i) : int {
			if ($i instanceof ItemBlock && ($b = $i->getBlock()) instanceof MobHead) {
				$type = $b->getMobHeadType();
				return ($type === MobHeadType::SKELETON || $type === MobHeadType::WITHER_SKELETON) ? 1 : 0;
			}
			return 0;
		};

		$items[] = ItemTypeIds::fromBlockTypeId(BlockTypeIds::CARVED_PUMPKIN);

		$items[] = static fn (Item $i) => $i->getCount(); // pickup all other items

		return $items;
	}

	protected function destroyCycles() : void{
		unset(
			$this->breakDoorGoal,
			$this->pickupItemsGoal,
		);
		parent::destroyCycles();
	}
}
