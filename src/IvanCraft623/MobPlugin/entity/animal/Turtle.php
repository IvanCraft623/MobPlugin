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

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MoveToLandGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MoveToWaterGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomSwimmingGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\turtle\GoHomeGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\turtle\LayEggGoal;
use IvanCraft623\MobPlugin\entity\ai\navigation\locomotion\AmphibiousLocomotion;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\item\ExtraItemTypeIds;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\libs\_c7b2bc3349bc5e96\IvanCraft623\Pathfinder\BlockPathType;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use function mt_rand;

class Turtle extends Animal{

	/** @var array<int, true> blocks it nests on, by type id */
	public const NESTING_BLOCKS = [
		BlockTypeIds::SAND => true,
		BlockTypeIds::RED_SAND => true
	];

	private const TAG_HOME_POSITION = "HomePos"; //TAG_List<TAG_Float>, x, y, z

	private const COMPONENT_GROUP_PREGNANT = "minecraft:pregnant";
	private const COMPONENT_GROUP_WANTS_TO_LAY_EGG = "minecraft:wants_to_lay_egg";

	private const MAX_HEALTH = 30; //health
	private const FOLLOW_RANGE = 1024; //blocks
	private const ADULT_WATER_SPEED = 0.12; //acceleration
	private const BABY_WATER_SPEED = 0.06; //acceleration
	private const BABY_SCALE = 0.16;
	private const BABY_CHANCE = 10; //1 in this many spawns is a baby

	public static function getNetworkTypeId() : string{ return EntityIds::TURTLE; }

	public static function getRandomStartAge() : int{
		return mt_rand(1, self::BABY_CHANCE) === 1 ? self::STARTING_BABY_AGE : self::ADULT_AGE;
	}

	/** Where it was born or spawned: it returns here to lay its eggs. */
	private Vector3 $homePosition;

	/** Whether it carries eggs that it has yet to take home. */
	private bool $pregnant = false;

	/** Whether it is home and looking for a nest. */
	private bool $wantsToLayEgg = false;

	private bool $layingEgg = false;

	/** @var Goal[] the goals that differ between the baby and the adult */
	private array $ageGoals = [];

	/**
	 * A baby turtle has its own, larger box that is then scaled down, unlike other mobs.
	 */
	protected function getInitialSizeInfo() : EntitySizeInfo{
		return $this->isBaby() ?
			new EntitySizeInfo(VanillaEntitySizes::TURTLE_BABY_HEIGHT, VanillaEntitySizes::TURTLE_BABY_WIDTH) :
			new EntitySizeInfo(VanillaEntitySizes::TURTLE_ADULT_HEIGHT, VanillaEntitySizes::TURTLE_ADULT_WIDTH);
	}

	public function getName() : string{
		return "Turtle";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(self::MAX_HEALTH);
		$this->setFollowRange(self::FOLLOW_RANGE);
		$this->navigation->setPathfindingMalus(BlockPathType::WATER, 0);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.1;
	}

	public function getWaterMovementSpeed() : float{
		return $this->isBaby() ? self::BABY_WATER_SPEED : self::ADULT_WATER_SPEED;
	}

	public function getBabyScale() : float{
		return self::BABY_SCALE;
	}

	/**
	 * Prefers sand, where it lays its eggs.
	 */
	public function getWalkTargetValue(Vector3 $position) : float{
		if (isset(self::NESTING_BLOCKS[$this->getWorld()->getBlock($position->down())->getTypeId()])) {
			return 10;
		}

		return parent::getWalkTargetValue($position);
	}

	public function createNavigation() : PathNavigation{
		return new PathNavigation($this, new AmphibiousLocomotion());
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new PanicGoal($this, 1.2, true));
		$this->goalSelector->addGoal(1, new GoHomeGoal($this, speedModifier: 1, interval: 700, goalRadius: 4));
		$this->goalSelector->addGoal(1, new LayEggGoal(
			$this,
			speedModifier: 1,
			searchRange: 16,
			searchHeight: 4,
			goalRadius: 1.5,
			layDuration: 200
		));
		$this->goalSelector->addGoal(2, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new TemptGoal($this, 1.1, false));
		$this->goalSelector->addGoal(7, new RandomSwimmingGoal($this, 1, 0, 30, 15));
		$this->goalSelector->addGoal(8, new LookAtEntityGoal($this, Player::class, 6, 0.02));
		$this->registerAgeGoals();
	}

	/**
	 * Babies head for the water first, adults go back and forth between the water and the shore.
	 */
	private function registerAgeGoals() : void{
		foreach ($this->ageGoals as $goal) {
			$this->goalSelector->removeGoal($goal);
		}
		$this->ageGoals = [];

		if ($this->isBaby()) {
			$this->addAgeGoal(1, new MoveToWaterGoal($this, 1, 15, 5));
		} else {
			$this->addAgeGoal(4, new MoveToWaterGoal($this, 1, 16, 5));
			$this->addAgeGoal(6, new MoveToLandGoal($this, 1, 16, 5));
			$this->addAgeGoal(9, new RandomStrollGoal($this, 1, 100, strollInsideWater: false));
		}
	}

	private function addAgeGoal(int $priority, Goal $goal) : void{
		$this->goalSelector->addGoal($priority, $goal);
		$this->ageGoals[] = $goal;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$home = $nbt->getListTag(self::TAG_HOME_POSITION, FloatTag::class);
		$this->homePosition = $home !== null && $home->count() === 3 ?
			new Vector3($home->get(0)->getValue(), $home->get(1)->getValue(), $home->get(2)->getValue()) :
			$this->location->asVector3();
		$this->pregnant = $this->componentGroups->has(self::COMPONENT_GROUP_PREGNANT);
		$this->wantsToLayEgg = $this->componentGroups->has(self::COMPONENT_GROUP_WANTS_TO_LAY_EGG);
	}

	public function saveNBT() : CompoundTag{
		$this->componentGroups->set(self::COMPONENT_GROUP_PREGNANT, $this->pregnant);
		$this->componentGroups->set(self::COMPONENT_GROUP_WANTS_TO_LAY_EGG, $this->wantsToLayEgg);

		$nbt = parent::saveNBT();
		$nbt->setTag(self::TAG_HOME_POSITION, new ListTag([
			new FloatTag($this->homePosition->x),
			new FloatTag($this->homePosition->y),
			new FloatTag($this->homePosition->z)
		], NBT::TAG_Float));

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::PREGNANT, $this->pregnant || $this->wantsToLayEgg);
		$properties->setGenericFlag(EntityMetadataFlags::LAYING_EGG, $this->layingEgg);
	}

	/**
	 * Whether predators go after it: a baby out of the water.
	 */
	public static function isBabyOnLand(Living $entity) : bool{
		return $entity instanceof self && $entity->isBaby() && !$entity->isInWater();
	}

	public function reachedAgeBoundary() : void{
		parent::reachedAgeBoundary();

		$this->registerAgeGoals();
		if (!$this->isBaby()) {
			$this->getWorld()->dropItem($this->location, ExtraVanillaItems::TURTLE_SCUTE());
		}
	}

	public function getHomePosition() : Vector3{
		return $this->homePosition;
	}

	/** @return $this */
	public function setHomePosition(Vector3 $position) : self{
		$this->homePosition = $position;

		return $this;
	}

	public function isPregnant() : bool{
		return $this->pregnant;
	}

	public function wantsToLayEgg() : bool{
		return $this->wantsToLayEgg;
	}

	/**
	 * Gets to where it was born with eggs, ready to look for a nest.
	 */
	public function arriveHome() : void{
		$this->pregnant = false;
		$this->wantsToLayEgg = true;
		$this->networkPropertiesDirty = true;
	}

	public function setLayingEgg(bool $layingEgg) : static{
		if ($layingEgg !== $this->layingEgg) {
			$this->layingEgg = $layingEgg;
			$this->networkPropertiesDirty = true;
		}

		return $this;
	}

	public function finishLayingEgg() : void{
		$this->wantsToLayEgg = false;
		$this->layingEgg = false;
		$this->networkPropertiesDirty = true;
	}

	/**
	 * Breeding makes this turtle carry the eggs instead of giving birth.
	 */
	public function spawnChildFromBreeding(Animal $partner) : void{
		$this->pregnant = true;
		$this->networkPropertiesDirty = true;

		$this->finishBreeding($partner);
	}

	/**
	 * Never drowns, it breathes both air and water.
	 */
	public function canBreathe() : bool{
		return true;
	}

	//TODO: lightning deals 2000 times the damage

	public function getBreedOffspring(AgeableMob $partner) : Turtle{
		throw new AssumptionFailedError("Turtles lay eggs, they don't give birth");
	}

	public function isFood(Item $item) : bool{
		return $item->getTypeId() === ExtraItemTypeIds::SEAGRASS();
	}

	public function getDrops() : array{
		return $this->isBaby() ? [] : $this->generateDeathLoot(VanillaLootTables::SEA_TURTLE());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::TURTLE_SPAWN_EGG();
	}
}