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

use IvanCraft623\MobPlugin\data\bedrock\RabbitCoatIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\goal\AvoidEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\rabbit\RaidGardenGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\animal\rabbit\RabbitMoveControl;
use IvanCraft623\MobPlugin\entity\HopperTrait;
use IvanCraft623\MobPlugin\entity\monster\Monster;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\spawning\BiomeTagMap;

use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\BlockPathType;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function mt_rand;

class Rabbit extends Animal {
	use HopperTrait;

	private const TAG_COAT = "Variant"; //TAG_Int

	private const MUTATION_CHANCE = 5; //one kit in this many gets a new coat
	//Vanilla draws the rabbit model bigger and scales the entity down, why? bedrock...
	private const ADULT_SCALE = 0.6;
	private const BABY_SCALE = 0.4;

	public static function getNetworkTypeId() : string{ return EntityIds::RABBIT; }

	public static function getRandomStartAge() : int{
		return mt_rand(1, 4) === 1 ? self::STARTING_BABY_AGE : self::ADULT_AGE;
	}

	/**
	 * A kit takes the coat of either parent, now and then a new one.
	 */
	public static function getOffspringCoat(Rabbit $parent1, Rabbit $parent2) : RabbitCoat{
		if (mt_rand(1, self::MUTATION_CHANCE) === 1) {
			return RabbitCoat::getRandom();
		}

		return mt_rand(0, 1) === 0 ? $parent1->getCoat() : $parent2->getCoat();
	}

	protected RabbitCoat $coat;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::RABBIT_ADULT_HEIGHT, VanillaEntitySizes::RABBIT_ADULT_WIDTH);
	}

	public function getName() : string{
		return "Rabbit";
	}

	public function getBabyScale() : float{
		return self::BABY_SCALE;
	}

	public function getAdultScale() : float{
		return self::ADULT_SCALE;
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setScale(self::ADULT_SCALE);
		$this->setMaxHealth(3);
		$this->navigation->setPathfindingMalus(BlockPathType::WATER, -1);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.3;
	}

	protected function createMoveControl() : MoveControl{
		return new RabbitMoveControl($this, $this->navigation);
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 2.2));
		$this->goalSelector->addGoal(2, new BreedGoal($this, 0.75));
		$this->goalSelector->addGoal(3, new TemptGoal($this, 1, false));
		$this->goalSelector->addGoal(4, new AvoidEntityGoal($this, [Player::class], 8, 1.5, 1.8));
		//TODO: Avoid wolves!
		$this->goalSelector->addGoal(4, new AvoidEntityGoal($this, [Monster::class, Slime::class], 4, 1.5, 1.5));
		$this->goalSelector->addGoal(5, new RaidGardenGoal($this, 0.6));
		$this->goalSelector->addGoal(6, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(6, new WaterAvoidingRandomStrollGoal($this, 0.6));
		$this->goalSelector->addGoal(11, new LookAtEntityGoal($this, Player::class, 10));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->coat = RabbitCoatIdMap::getInstance()->fromId($nbt->getInt(self::TAG_COAT, -1)) ?? $this->getSpawnCoat();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_COAT, RabbitCoatIdMap::getInstance()->toId($this->coat));

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, RabbitCoatIdMap::getInstance()->toId($this->coat));
	}

	private function getSpawnCoat() : RabbitCoat{
		$world = $this->getWorld();
		$x = $this->location->getFloorX();
		$y = $this->location->getFloorY();
		$z = $this->location->getFloorZ();

		if ($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::SNOW_LAYER ||
			$world->getBlockAt($x, $y - 1, $z)->getTypeId() === BlockTypeIds::SNOW
		) {
			return RabbitCoat::getRandomSnow();
		}

		if (BiomeTagMap::getInstance()->hasTag($world->getBiomeId($x, $y, $z), "desert")) {
			return RabbitCoat::DESERT;
		}

		return RabbitCoat::getRandom();
	}

	public function getCoat() : RabbitCoat{
		return $this->coat;
	}

	/**
	 * @return $this
	 */
	public function setCoat(RabbitCoat $coat) : self{
		$this->coat = $coat;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function isFood(Item $item) : bool{
		$typeId = $item->getTypeId();

		return $typeId === ItemTypeIds::CARROT ||
			$typeId === ItemTypeIds::GOLDEN_CARROT ||
			$typeId === VanillaBlocks::DANDELION()->asItem()->getTypeId();
	}

	public function getBreedOffspring(AgeableMob $partner) : Rabbit{
		$offspring = new Rabbit($this->getLocation());

		/** @var Rabbit $partner */
		return $offspring->setCoat(self::getOffspringCoat($this, $partner));
	}

	public function getDrops() : array{
		return $this->isBaby() ? [] : $this->generateDeathLoot(VanillaLootTables::RABBIT());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::RABBIT_SPAWN_EGG();
	}
}