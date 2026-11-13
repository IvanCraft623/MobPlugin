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

use IvanCraft623\MobPlugin\data\bedrock\CatVariantIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\FollowOwnerGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\SitOnBlockGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\SitWhenOrderedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\TeleportToOwnerGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\utils\BlockMatcher;
use IvanCraft623\MobPlugin\entity\Collared;
use IvanCraft623\MobPlugin\entity\CollaredTrait;
use IvanCraft623\MobPlugin\entity\Healable;
use IvanCraft623\MobPlugin\entity\HealableTrait;
use IvanCraft623\MobPlugin\entity\Pet;
use IvanCraft623\MobPlugin\entity\PetTrait;
use IvanCraft623\MobPlugin\entity\TameableTrait;
use IvanCraft623\MobPlugin\entity\TameByItemTrait;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Furnace;
use pocketmine\block\tile\Chest as ChestTile;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function count;
use function mt_rand;

class Cat extends Feline implements Pet, Collared, Healable{
	use TameableTrait, TameByItemTrait, PetTrait, CollaredTrait, HealableTrait{
		//initEntity()
		TameableTrait::initEntity insteadof PetTrait, CollaredTrait;
		TameableTrait::initEntity as protected initTameable;
		PetTrait::initEntity as protected initPet;
		CollaredTrait::initEntity as protected initCollared;

		//saveNBT()
		TameableTrait::saveNBT insteadof PetTrait, CollaredTrait;
		TameableTrait::saveNBT as protected saveTameable;
		PetTrait::saveNBT as protected savePet;
		CollaredTrait::saveNBT as protected saveCollared;

		//syncNetworkData()
		TameableTrait::syncNetworkData insteadof PetTrait, CollaredTrait;
		TameableTrait::syncNetworkData as protected syncTameable;
		PetTrait::syncNetworkData as protected syncPet;
		CollaredTrait::syncNetworkData as protected syncCollared;

		//onInteract()
		TameByItemTrait::onInteract insteadof PetTrait, CollaredTrait, HealableTrait;
		TameByItemTrait::onInteract as protected interactTameByItem;
		PetTrait::onInteract as protected interactPet;
		CollaredTrait::onInteract as protected interactCollared;
		HealableTrait::onInteract as protected interactHealable;

		//tame()
		TameableTrait::tame as protected tameByPlayer;

		//onDeath()
		TameableTrait::onDeath as protected onTameableDeath;
	}

	private const TAG_VARIANT = "Variant"; //TAG_Int

	private const WILD_MAX_HEALTH = 10; //health
	private const TAMED_MAX_HEALTH = 20; //health
	private const ATTACK_DAMAGE = 4; //health
	private const HEAL_AMOUNT = 2.0; //health
	private const TAME_CHANCE = 0.33; //probability per fish

	private const BABY_SCALE = 0.4;
	private const ADULT_SCALE = 0.8;

	public static function getNetworkTypeId() : string{ return EntityIds::CAT; }

	protected CatVariant $variant;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::CAT_HEIGHT, VanillaEntitySizes::CAT_WIDTH);
	}

	public function getName() : string{
		return "Cat";
	}

	public function getBabyScale() : float{
		return self::BABY_SCALE;
	}

	public function getAdultScale() : float{
		return self::ADULT_SCALE;
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth($this->isTamed() ? self::TAMED_MAX_HEALTH : self::WILD_MAX_HEALTH);
		$this->setAttackDamage(self::ATTACK_DAMAGE);
	}

	protected function registerGoals() : void{
		parent::registerGoals();

		$this->goalSelector->addGoal(0, new TeleportToOwnerGoal($this, 12));
		$this->goalSelector->addGoal(3, new SitWhenOrderedGoal($this));
		$this->goalSelector->addGoal(4, new FollowOwnerGoal($this, 1, 10, 2, 12));
		$this->goalSelector->addGoal(7, new SitOnBlockGoal($this, 1, [
			new BlockMatcher(BlockTypeIds::CHEST, static function(Block $block) : bool{
				$position = $block->getPosition();
				$tile = $position->getWorld()->getTileAt($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());

				return !$tile instanceof ChestTile || count($tile->getInventory()->getViewers()) === 0;
			}),
			new BlockMatcher(BlockTypeIds::FURNACE, static fn(Block $block) => $block instanceof Furnace && $block->isLit()),
			new BlockMatcher(BlockTypeIds::BED, static fn(Block $block) => $block instanceof Bed && !$block->isHeadPart()),
		]));
		//TODO: sleep with the owner and bring them gifts in the morning!

		$this->targetSelector->addGoal(1, new NearestAttackableGoal(entity: $this, targetType: Rabbit::class, condition: fn() => !$this->isTamed(), range: 8));
		//TODO: Wild cats also hunt baby turtles!
	}

	protected function initEntity(CompoundTag $nbt) : void{
		//The stats depend on it
		$this->initTameable($nbt);

		parent::initEntity($nbt);

		$this->initPet($nbt);
		$this->initCollared($nbt);
		$this->variant = CatVariantIdMap::getInstance()->fromId($nbt->getInt(self::TAG_VARIANT, -1)) ?? $this->getSpawnVariant();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT()
			->merge($this->saveTameable())
			->merge($this->savePet())
			->merge($this->saveCollared());
		$nbt->setInt(self::TAG_VARIANT, CatVariantIdMap::getInstance()->toId($this->variant));

		return $nbt;
	}

	protected function onDeath() : void{
		parent::onDeath();
		$this->onTameableDeath();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$this->syncTameable($properties);
		$this->syncPet($properties);
		$this->syncCollared($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, CatVariantIdMap::getInstance()->toId($this->variant));
	}

	//TODO: the sound variant, once entity properties are in

	private function getSpawnVariant() : CatVariant{
		$variants = CatVariant::cases();

		return $variants[mt_rand(0, count($variants) - 1)];
	}

	public function getVariant() : CatVariant{
		return $this->variant;
	}

	/**
	 * @return $this
	 */
	public function setVariant(CatVariant $variant) : self{
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function isWary() : bool{
		return !$this->isTamed();
	}

	protected function onTamed() : void{
		$this->setMaxHealth(self::TAMED_MAX_HEALTH);
		$this->setHealth($this->getMaxHealth());
		if ($this->isOrphaned()) {
			$this->setSitting(true);
		}
	}

	/**
	 * Sits when a player tames it; kittens are born tamed.
	 */
	public function tame(Player $tamer) : bool{
		if (!$this->tameByPlayer($tamer)) {
			return false;
		}
		$this->setSitting(true);

		return true;
	}

	protected function isTameItem(Item $item) : bool{
		return $this->isFood($item);
	}

	protected function getTameChance() : float{
		return self::TAME_CHANCE;
	}

	public function getHealAmount(Item $item) : float{
		return $this->isFood($item) ? self::HEAL_AMOUNT : 0.0;
	}

	public function canFallInLove() : bool{
		return $this->isTamed() && $this->getHealth() >= $this->getMaxHealth() && parent::canFallInLove();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$isOwner = $this->isTamed() && $this->getOwningEntityId() === $player->getId();

		//Fish tames a wild cat, even a kitten, so it comes before feeding
		return $this->interactTameByItem($player, $clickPos) ||
			($this->isTamed() && $this->interactHealable($player, $clickPos)) ||
			parent::onInteract($player, $clickPos) ||
			($isOwner && $this->interactCollared($player, $clickPos)) ||
			(($isOwner || $this->isOrphaned()) && $this->interactPet($player, $clickPos));
	}

	public function getBreedOffspring(AgeableMob $partner) : Cat{
		/** @var Cat $partner */
		$offspring = new Cat($this->getLocation());
		if ($this->isTamed()) {
			$offspring->becomeTamed($this->getOwningEntity());
			$collar1 = $this->getCollarColor();
			$collar2 = $partner->getCollarColor();
			$offspring->setCollarColor(Utils::mixDyeColors($collar1, $collar2) ?? (mt_rand(0, 1) === 0 ? $collar1 : $collar2));
		}
		$offspring->setVariant(mt_rand(0, 1) === 0 ? $this->variant : $partner->variant);

		return $offspring;
	}

	public function getDrops() : array{
		return $this->isBaby() ? [] : $this->generateDeathLoot(VanillaLootTables::CAT());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::CAT_SPAWN_EGG();
	}
}
