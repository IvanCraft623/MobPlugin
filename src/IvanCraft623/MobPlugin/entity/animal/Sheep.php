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
use IvanCraft623\MobPlugin\entity\ai\goal\EatBlockGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\Shearable;
use IvanCraft623\MobPlugin\sound\ShearSound;
use IvanCraft623\MobPlugin\utils\ItemSet;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\utils\ColoredTrait;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Dye;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function mt_rand;

class Sheep extends Animal implements Shearable{
	use ColoredTrait {
		setColor as traitSetColor;
	}

	private const TAG_COLOR = "Color"; //TAG_Byte
	private const TAG_SHEARED = "Sheared"; //TAG_Byte

	public static function getNetworkTypeId() : string{ return EntityIds::SHEEP; }

	public static function getRandomSheepColor() : DyeColor{
		$probability = mt_rand(0, 100);
		if ($probability < 5) {
			return DyeColor::BLACK();
		}
		if ($probability < 10) {
			return DyeColor::GRAY();
		}
		if ($probability < 15) {
			return DyeColor::LIGHT_GRAY();
		}
		if ($probability < 18) {
			return DyeColor::BROWN();
		}

		return mt_rand(0, 500) === 0 ? DyeColor::PINK() : DyeColor::WHITE();
	}

	public static function getOffspringColor(Sheep $parent1, Sheep $parent2) : DyeColor{
		//In java if the colors can be combined it returns the resulting color
		//However in bedrock a random color is chosen from the parents.

		return mt_rand(0, 1) === 0 ? $parent1->getColor() : $parent2->getColor();
	}

	protected bool $sheared = false;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.3, 0.9, 1.235);
	}

	public function getName() : string{
		return "Sheep";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 1.25));
		$this->goalSelector->addGoal(2, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new TemptGoal($this, 1.25, (new ItemSet())->add(VanillaItems::WHEAT()), false));
		$this->goalSelector->addGoal(4, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(5, new EatBlockGoal($this));
		$this->goalSelector->addGoal(6, new WaterAvoidingRandomStrollGoal($this, 0.8));
		$this->goalSelector->addGoal(7, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(8, new RandomLookAroundGoal($this));
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(8);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$color = DyeColorIdMap::getInstance()->fromId($nbt->getByte(self::TAG_COLOR, -1));
		$this->setColor($color ?? static::getRandomSheepColor());

		$this->sheared = $nbt->getByte(self::TAG_SHEARED, 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setByte(self::TAG_COLOR, DyeColorIdMap::getInstance()->toId($this->getColor()));
		$nbt->setByte(self::TAG_SHEARED, $this->isSheared() ? 1 : 0);

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setByte(EntityMetadataProperties::COLOR, DyeColorIdMap::getInstance()->toId($this->getColor()));

		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->isSheared());
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if ($item->getTypeId() === ItemTypeIds::SHEARS && $this->isReadyForShearing()) {
			$this->shear();
			Utils::damageItemInHand($player);

			return true;
		}

		if (!$this->isSheared() &&
			$item instanceof Dye &&
			!($color = $item->getColor())->equals($this->getColor())
		) {
			$this->setColor($color);
			Utils::popItemInHand($player);

			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	public function setColor(DyeColor $color) : self{
		$this->networkPropertiesDirty = true;

		return $this->traitSetColor($color);
	}

	public function shear() : void{
		$this->broadcastSound(new ShearSound());
		$this->setSheared();

		$this->getWorld()->dropItem(
			$this->location->add(0, $this->getSize()->getHeight(), 0),
			VanillaBlocks::WOOL()->setColor($this->getColor())->asItem()->setCount(mt_rand(1, 3))
		);
	}

	public function setSheared(bool $sheared = true) : void{
		$this->sheared = $sheared;

		$this->networkPropertiesDirty = true;
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function isReadyForShearing() : bool{
		return $this->isAlive() && !$this->isSheared() && !$this->isBaby();
	}

	public function getBreedOffspring(AgeableMob $partner) : Sheep{
		$offspring = new Sheep($this->getLocation());

		/** @var Sheep $partner */
		$offspring->setColor(static::getOffspringColor($this, $partner));

		return $offspring;
	}

	public function onEat() : void{
		//In bedrock baby sheeps doesn't increase it's age?
		$this->setSheared(false);
	}

	public function getDrops() : array{
		$drops = [];
		if (!$this->isBaby()) {
			$drops[] = ($this->shouldDropCookedItems() ? VanillaItems::COOKED_MUTTON() : VanillaItems::RAW_MUTTON())->setCount(mt_rand(1, 2));

			if (!$this->isSheared()) {
				$drops[] = VanillaBlocks::WOOL()->setColor($this->getColor())->asItem();
			}
		}

		return $drops;
	}

	//TODO: natural spawning logic
}
