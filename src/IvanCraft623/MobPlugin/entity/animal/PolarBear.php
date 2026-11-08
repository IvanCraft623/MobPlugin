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
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\polarbear\PolarBearAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\NeutralMob;
use IvanCraft623\MobPlugin\entity\NeutralMobTrait;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use function mt_rand;

/**
 * Peaceful until hurt, or until a cub sees a player.
 */
class PolarBear extends Animal implements NeutralMob {
	use NeutralMobTrait;

	private const ADULT_ANGER_TICKS = 500;
	private const BABY_ANGER_TICKS = 1;
	private const CUB_ALERT_RANGE = 41; //blocks
	private const CUB_TARGET_RANGE = 16; //blocks
	private const BABY_CHANCE = 10; //one baby in this many spawns

	private bool $rearing = false;

	private bool $wasAngry = false;

	public static function getNetworkTypeId() : string{ return EntityIds::POLAR_BEAR; }

	public static function getRandomStartAge() : int{
		return mt_rand(1, self::BABY_CHANCE) === 1 ? self::STARTING_BABY_AGE : self::ADULT_AGE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::POLAR_BEAR_HEIGHT, VanillaEntitySizes::POLAR_BEAR_WIDTH);
	}

	public function getName() : string{
		return "Polar Bear";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(30);
		$this->setAttackDamage(6);
		$this->setFollowRange(48);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PolarBearAttackGoal($this, 1));
		$this->goalSelector->addGoal(1, new class($this, 1.25) extends PanicGoal{
			protected function shouldPanic() : bool{
				return $this->entity->isOnFire();
			}
		});
		$this->goalSelector->addGoal(4, new FollowParentGoal($this, 1.25));
		$this->goalSelector->addGoal(5, new RandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 6, 0.02));
		$this->goalSelector->addGoal(7, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(2, new HurtByTargetGoal($this));
		//A cub that sees a player calls the adults, see startAngerTimer()
		$cubGoal = new NearestAttackableGoal($this, Player::class, condition: fn() : bool => $this->isBaby());
		$cubGoal->getTargetingConditions()->setRange(self::CUB_TARGET_RANGE);
		$this->targetSelector->addGoal(4, $cubGoal);
		//TODO: Attack foxes!
	}

	public function setRearing(bool $rearing) : void{
		if ($rearing !== $this->rearing) {
			$this->rearing = $rearing;
			$this->networkPropertiesDirty = true;
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::REARING, $this->rearing);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry());
	}

	public function getAmbientSoundEvent() : string{
		return $this->isBaby() ? LevelSoundEvent::AMBIENT_BABY : LevelSoundEvent::AMBIENT;
	}

	public function isFood(Item $item) : bool{
		return false;
	}

	public function getBreedOffspring(AgeableMob $partner) : PolarBear{
		return new PolarBear($this->getLocation());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$this->updateAnger(true);

		if ($this->isAngry() !== $this->wasAngry) {
			$this->wasAngry = $this->isAngry();
			$this->networkPropertiesDirty = true;
		}

		return $hasUpdate;
	}

	public function startAngerTimer() : void{
		$this->setRemainingAngerTicks($this->isBaby() ? self::BABY_ANGER_TICKS : self::ADULT_ANGER_TICKS);

		$target = $this->getTargetEntity();
		if ($this->isBaby() && $target !== null) {
			$this->alertAdults($target);
		}
	}

	private function alertAdults(Entity $target) : void{
		foreach ($this->getSensing()->getNearbyLiving(self::CUB_ALERT_RANGE, HurtByTargetGoal::ALERT_RANGE_Y) as $other) {
			if ($other instanceof PolarBear && !$other->isBaby() && $other->getTargetEntityId() === null) {
				$other->setTargetEntity($target);
			}
		}
	}

	public function getDrops() : array{
		return $this->isBaby() ? [] : $this->generateDeathLoot(VanillaLootTables::POLAR_BEAR());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::POLAR_BEAR_SPAWN_EGG();
	}
}
