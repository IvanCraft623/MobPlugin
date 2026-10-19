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

use IvanCraft623\MobPlugin\data\bedrock\AxolotlVariantIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\control\AquaticMoveControl;
use IvanCraft623\MobPlugin\entity\ai\goal\axolotl\AxolotlAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\axolotl\HuntPreyGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\axolotl\PlayDeadGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MoveToWaterGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomSwimmingGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\SwimIdleGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\navigation\AmphibiousSwimNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\Bucketable;
use IvanCraft623\MobPlugin\entity\BucketableTrait;
use IvanCraft623\MobPlugin\item\ExtraItemTypeIds;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\item\MobBucket;
use IvanCraft623\Pathfinder\BlockPathType;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use function mt_rand;

class Axolotl extends Animal implements Bucketable{
	use BucketableTrait{
		onInteract as private onBucketInteract;
	}

	private const TAG_VARIANT = "Variant"; //TAG_Int

	public const POTION_HYDRATION_TICKS = 1800;
	public const BUFF_RANGE = 20;
	public const BUFF_TICKS = 100;
	public const MAX_REGENERATION_BUFF_TICKS = 2400;

	private const MAX_AIR = 6000;
	private const BLUE_BABY_CHANCE = 1200;
	private const HUNTING_COOLDOWN_TICKS = 2400;
	private const DRY_OUT_DAMAGE = 1;

	public static function getNetworkTypeId() : string{ return EntityIds::AXOLOTL; }

	/**
	 * Axolotls always spawn as adults: babies only come from breeding and from the herds of the
	 * spawn rules.
	 */
	public static function getRandomStartAge() : int{
		return self::ADULT_AGE;
	}

	public static function getOffspringVariant(Axolotl $parent1, Axolotl $parent2) : AxolotlVariant{
		if(mt_rand(0, self::BLUE_BABY_CHANCE - 1) === 0){
			return AxolotlVariant::BLUE;
		}

		return mt_rand(0, 1) === 0 ? $parent1->getVariant() : $parent2->getVariant();
	}

	protected AxolotlVariant $variant;

	private int $huntingCooldownEndTick = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::AXOLOTL_HEIGHT, VanillaEntitySizes::AXOLOTL_WIDTH);
	}

	public function getName() : string{
		return "Axolotl";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(14);
		$this->setAttackDamage(2);
		$this->setPathfindingMalus(BlockPathType::WATER, 0);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.1;
	}

	public function getWaterMovementSpeed() : float{
		return 0.2;
	}

	public function getWalkTargetValue(Vector3 $position) : float{
		return 0;
	}

	public function getAmbientSoundInterval() : float{
		return 6;
	}

	public function getAmbientSoundEvent() : string{
		return $this->isUnderwater() ? LevelSoundEvent::AMBIENT_IN_WATER : LevelSoundEvent::AMBIENT;
	}

	public function createNavigation() : PathNavigation{
		return new AmphibiousSwimNavigation($this);
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new PlayDeadGoal($this));
		$this->goalSelector->addGoal(1, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(2, new TemptGoal($this, 1.1, false));
		$this->goalSelector->addGoal(4, new AxolotlAttackGoal($this));
		$this->goalSelector->addGoal(5, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(6, new MoveToWaterGoal($this, 1, 16, 5));
		$this->goalSelector->addGoal(7, new SwimIdleGoal($this, 100, 0.05));
		$this->goalSelector->addGoal(8, new RandomSwimmingGoal($this, 1, 0, 30, 15));
		$this->goalSelector->addGoal(9, new RandomStrollGoal($this, 1, 100, strollInsideWater: false));
		$this->goalSelector->addGoal(10, new LookAtEntityGoal($this, Player::class, 6, 0.02));

		$this->targetSelector->addGoal(3, new HuntPreyGoal($this));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->moveControl = new AquaticMoveControl($this, maxPitchChange: 85, maxYawChange: 10, waterSpeed: 1, landSpeed: 1, buoyant: false);

		$this->variant = AxolotlVariantIdMap::getInstance()->fromId($nbt->getInt(self::TAG_VARIANT, -1)) ?? AxolotlVariant::getRandomNatural();

		$this->setMaxAirSupplyTicks(self::MAX_AIR);
		$this->setAirSupplyTicks(self::MAX_AIR);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_VARIANT, AxolotlVariantIdMap::getInstance()->toId($this->variant));

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, AxolotlVariantIdMap::getInstance()->toId($this->variant));
	}

	/**
	 * Dries out on land, and recovers in water.
	 */
	public function canBreathe() : bool{
		return $this->isInWater();
	}

	/**
	 * Back in water the supply is full again at once, like in vanilla.
	 */
	protected function doAirSupplyTick(int $tickDiff) : bool{
		if(!$this->canBreathe() || $this->getAirSupplyTicks() >= $this->getMaxAirSupplyTicks()){
			return parent::doAirSupplyTick($tickDiff);
		}

		$this->setAirSupplyTicks($this->getMaxAirSupplyTicks());
		$this->setBreathing();

		return true;
	}

	public function onAirExpired() : void{
		$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, self::DRY_OUT_DAMAGE));
	}

	public function isHuntingOnCooldown() : bool{
		return $this->getServer()->getTick() < $this->huntingCooldownEndTick;
	}

	public function startHuntingCooldown() : void{
		$this->huntingCooldownEndTick = $this->getServer()->getTick() + self::HUNTING_COOLDOWN_TICKS;
	}

	public function getVariant() : AxolotlVariant{
		return $this->variant;
	}

	/** @return $this */
	public function setVariant(AxolotlVariant $variant) : self{
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function isFood(Item $item) : bool{
		return $item->getTypeId() === ExtraItemTypeIds::TROPICAL_FISH_BUCKET();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->onBucketInteract($player, $clickPos)){
			return true;
		}

		$item = $player->getInventory()->getItemInHand();
		$handled = parent::onInteract($player, $clickPos);

		if($handled && $item instanceof MobBucket && $this->isFood($item) && $player->hasFiniteResources()){
			$player->getInventory()->setItemInHand(VanillaItems::WATER_BUCKET());
		}

		return $handled;
	}

	//TODO: golden dandelion pauses and resets the growth of babies

	//TODO: lightning deals 2000 times the damage

	public function getBreedOffspring(AgeableMob $partner) : Axolotl{
		$offspring = new Axolotl($this->getLocation());

		/** @var Axolotl $partner */
		return $offspring->setVariant(self::getOffspringVariant($this, $partner));
	}

	public function getBucketItem() : MobBucket{
		return ExtraVanillaItems::AXOLOTL_BUCKET();
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::AXOLOTL_SPAWN_EGG();
	}
}
