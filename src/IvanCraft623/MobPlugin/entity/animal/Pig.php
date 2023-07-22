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
use IvanCraft623\MobPlugin\entity\ItemSteerable;
use IvanCraft623\MobPlugin\entity\Saddleable;
use IvanCraft623\MobPlugin\utils\ItemSet;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function mt_rand;

class Pig extends Animal implements ItemSteerable, Saddleable {

	private const TAG_SADDLED = "Saddled"; //TAG_Byte

	public static function FOOD_ITEMS() : ItemSet{
		return (new ItemSet())->add(
			VanillaItems::CARROT(),
			VanillaItems::BEETROOT(),
			VanillaItems::POTATO()
		);
	}

	public static function getNetworkTypeId() : string{ return EntityIds::PIG; }

	protected bool $saddled = false;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.9, 0.9, 0.765);
	}

	public function getName() : string{
		return "Pig";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 1.25));
		$this->goalSelector->addGoal(2, new BreedGoal($this, 1));
		//TOOD: carrot on a stick tempt
		$this->goalSelector->addGoal(3, new TemptGoal($this, 1.2, self::FOOD_ITEMS(), false));
		$this->goalSelector->addGoal(4, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(7, new RandomLookAroundGoal($this));
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(10);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->saddled = $nbt->getByte(self::TAG_SADDLED, 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setByte(self::TAG_SADDLED, $this->isSaddled() ? 1 : 0);

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::SADDLED, $this->isSaddled());
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		//TODO: saddle logic

		return parent::onInteract($player, $clickPos);
	}

	public function getBreedOffspring(AgeableMob $partner) : Pig{
		return new Pig($this->getLocation());
	}

	public function saddle() : void{
		//TODO!
	}

	public function setSaddled(bool $saddled = true) : void{
		$this->saddled = $saddled;

		$this->networkPropertiesDirty = true;
	}

	public function isSaddled() : bool{
		return $this->saddled;
	}

	public function canBeSaddled() : bool{
		return !$this->isBaby() && !$this->isSaddled();
	}

	public function isSteerItem() : bool{
		return false; //TODO!
	}

	public function boost() : bool{
		return false; //TODO!
	}

	public function getDrops() : array{
		$drops = [];
		if (!$this->isBaby()) {
			//TODO: saddle
			$drops[] = ($this->shouldDropCookedItems() ? VanillaItems::COOKED_PORKCHOP() : VanillaItems::RAW_PORKCHOP())->setCount(mt_rand(1, 3));
		}

		return $drops;
	}

	public function isFood(Item $item) : bool {
		return self::FOOD_ITEMS()->contains($item);
	}

	public function onLightningBoltHit() : bool{
		//TODO!
		return parent::onLightningBoltHit();
	}
}
