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

namespace IvanCraft623\MobPlugin\entity\animal;

use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\sound\EntityPlopSound;
use IvanCraft623\MobPlugin\utils\ItemSet;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\utils\Binary;
use function mt_rand;

class Chicken extends Animal {

	public const TAG_ENTRIES = "entries"; //TAG_List
	public const TAG_EGG_LAYING_DELAY = "SpawnTimer"; //TAG_Int
	public const TAG_STOP_LAYING_EGGS = "StopSpawning"; //TAG_Byte

	public static function FOOD_ITEMS() : ItemSet{
		return (new ItemSet())->add(
			VanillaItems::WHEAT_SEEDS(),
			VanillaItems::BEETROOT_SEEDS(),
			VanillaItems::MELON_SEEDS(),
			VanillaItems::PUMPKIN_SEEDS()
			//TODO: pitcher pod & torchflower seeds
		);
	}

	public static function getNetworkTypeId() : string{ return EntityIds::CHICKEN; }

	public static function getRandomEggLayingDelay() : int{
		return mt_rand(6000, 12000);
	}

	protected int $eggLayingDelay; //in ticks

	protected bool $canLayEggs = true;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.8, 0.6, 0.7);
	}

	public function getName() : string{
		return "Chicken";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 1.5));
		$this->goalSelector->addGoal(2, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new TemptGoal($this, 1.25, self::FOOD_ITEMS(), false));
		$this->goalSelector->addGoal(4, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomStrollGoal($this, 0.8));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(7, new RandomLookAroundGoal($this));
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(4);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		if (($entries = $nbt->getListTag(self::TAG_ENTRIES)) !== null) { //wtf mojang?
			/** @var CompoundTag $entry */
			$entry = $entries->first();

			$this->eggLayingDelay = $entry->getInt(self::TAG_EGG_LAYING_DELAY, self::getRandomEggLayingDelay());
			$this->canLayEggs = $entry->getByte(self::TAG_STOP_LAYING_EGGS, 0) !== 1;
		} else {
			$this->eggLayingDelay = self::getRandomEggLayingDelay();
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setTag(self::TAG_ENTRIES, new ListTag([CompoundTag::create()
			->setInt(self::TAG_EGG_LAYING_DELAY, Binary::signInt($this->eggLayingDelay))
			->setByte(self::TAG_STOP_LAYING_EGGS, !$this->canLayEggs ? 1 : 0)
		], NBT::TAG_Compound));

		return $nbt;
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	protected function tryChangeMovement() : void{
		if (!$this->onGround && $this->motion->y < 0) {
			$this->motion = new Vector3($this->motion->x, $this->motion->y * 0.6, $this->motion->z);
		}

		parent::tryChangeMovement();
	}

	protected function calculateFallDamage(float $fallDistance) : float{
		return 0;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if ($this->canLayEggs && !$this->isBaby() && --$this->eggLayingDelay <= 0) {
			//TODO: check that there no riders
			$this->broadcastSound(new EntityPlopSound($this));
			$this->getWorld()->dropItem($this->location, VanillaItems::EGG());

			$this->eggLayingDelay = self::getRandomEggLayingDelay();
		}

		return $hasUpdate;
	}

	public function getBreedOffspring(AgeableMob $partner) : Chicken{
		return new Chicken($this->getLocation());
	}

	public function getEggLayingDelay() : int{
		return $this->eggLayingDelay;
	}

	public function setEggLayingDelay(int $delay) : self{
		$this->eggLayingDelay = $delay;

		return $this;
	}

	public function canLayEggs() : bool{
		return $this->canLayEggs;
	}

	/**
	 * @return $this
	 */
	public function setCanLayEggs(bool $value) : self{
		$this->canLayEggs = $value;

		return $this;
	}

	public function getDrops() : array{
		$drops = [];
		if (!$this->isBaby()) {
			$drops = [
				VanillaItems::FEATHER()->setCount(mt_rand(0, 2)),
				$this->shouldDropCookedItems() ? VanillaItems::COOKED_CHICKEN() : VanillaItems::RAW_CHICKEN()
			];
		}

		return $drops;
	}

	public function isFood(Item $item) : bool {
		return self::FOOD_ITEMS()->contains($item);
	}
}
