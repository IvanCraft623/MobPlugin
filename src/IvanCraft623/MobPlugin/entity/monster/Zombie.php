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

use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\BreakDoorGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\DestroyEggGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\sound\SoundEvents;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Ageable;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Villager;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\World;

use function count;
use function mt_rand;

class Zombie extends Monster implements Ageable {

	private const TAG_IS_BABY = "IsBaby"; //TAG_Int

	private const COMPONENT_GROUP_CAN_BREAK_DOORS = "minecraft:can_break_doors";

	private const WATER_CONVERSION_TIME = 300;
	private const MAX_WATER_TIME_BEFORE_CONVERSION = 600;

	private bool $isBaby = false;

	private bool $canBreakDoors = false;

	private int $inWaterTime = 0;

	private int $ticksUntilWaterConversion = -1;

	private bool $isConvertingInWater = false;

	private BreakDoorGoal $breakDoorGoal;

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

	public function canBreakDoors() : bool {
		return $this->canBreakDoors;
	}

	/**
	 * @return $this
	 */
	public function setCanBreakDoors(bool $value = true) : self{
		if ($this->canBreakDoors !== $value) {
			$this->canBreakDoors = $value;

			if ($value) {
				$this->goalSelector->addGoal(1, $this->breakDoorGoal);
			} else {
				$this->goalSelector->removeGoal($this->breakDoorGoal);
			}
		}
		return $this;
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

		if ($this->isBaby()) {
			$drops[] = VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 1));
		} else {
			$drops[] = VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(1, 2));
		}

		// Rare iron drop
		if (mt_rand(0, 199) < 5) { // 2.5% chance
			switch(mt_rand(0, 2)) {
				case 0:
					$drops[] = VanillaItems::IRON_INGOT();
					break;
				case 1:
					$drops[] = VanillaItems::CARROT();
					break;
				case 2:
					$drops[] = VanillaItems::POTATO();
					break;
			}
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

		$isBabyByte = $nbt->getByte(self::TAG_IS_BABY, -1);
		if ($isBabyByte === 1 || ($isBabyByte === -1 && AgeableMob::getRandomStartAge() !== AgeableMob::ADULT_AGE)) {
			$this->setBaby();
			//TODO: 0.75% chance of zombie jockey
		}

		$this->canBreakDoors = $this->componentGroups->has(self::COMPONENT_GROUP_CAN_BREAK_DOORS);

		// Halloween pumpkin head chance, this is a java only feature, but whhy not?
		if ($this->getArmorInventory()->getHelmet()->isNull() && Utils::isHalloween() && mt_rand(1, 4) === 1) {
			$this->getArmorInventory()->setHelmet(
				mt_rand(1, 10) === 1 ?
				VanillaBlocks::LIT_PUMPKIN()->asItem() :
				VanillaBlocks::CARVED_PUMPKIN()->asItem()
			);
		}
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT();

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
}
