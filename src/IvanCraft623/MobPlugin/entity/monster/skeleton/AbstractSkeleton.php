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

namespace IvanCraft623\MobPlugin\entity\monster\skeleton;

use IvanCraft623\MobPlugin\entity\ai\goal\AvoidSunlightGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FleeSunlightGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PickupItemsGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RangedBowAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\ItemPickupCapable;
use IvanCraft623\MobPlugin\entity\ItemPickupCapableTrait;
use IvanCraft623\MobPlugin\entity\MobType;
use IvanCraft623\MobPlugin\entity\monster\Monster;
use IvanCraft623\MobPlugin\entity\RangedAttackMob;
use IvanCraft623\MobPlugin\inventory\MobInventory;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\sound\BowShootSound;
use pocketmine\world\World;
use function mt_rand;
use function sqrt;

abstract class AbstractSkeleton extends Monster implements RangedAttackMob, ItemPickupCapable {
	use ItemPickupCapableTrait {
		initEntity as initEntityPickupTrait;
		saveNBT as saveNBTPickupTrait;
	}

	protected const HARD_ATTACK_INTERVAL = 20;
	protected const REGULAR_ATTACK_INTERVAL = 40;

	private RangedBowAttackGoal $bowAttackGoal;
	private MeleeAttackGoal $meleeAttackGoal;

	private PickupItemsGoal $pickupItemsGoal;

	public function getMobType() : MobType{
		return MobType::UNDEAD();
	}

	protected function registerGoals() : void{
		$this->pickupItemsGoal = (new PickupItemsGoal($this, 1, 2, 4))->setWantedItems(static::getWantedItems());

		$this->goalSelector->addGoal(2, new AvoidSunlightGoal($this));
		$this->goalSelector->addGoal(3, new FleeSunlightGoal($this, 1));
		//TODO: 3 flee wolfs
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(6, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, (new HurtByTargetGoal($this))->setAlertOthers());
		$this->targetSelector->addGoal(2, new NearestAttackableGoal(entity: $this, targetType: Player::class, mustSee: true));
		$this->targetSelector->addGoal(3, new NearestAttackableGoal(entity: $this, targetType: IronGolem::class, mustSee: true));
		//TODO: attack baby turtles

		$this->bowAttackGoal = new RangedBowAttackGoal($this, 1, 20, 15);
		$this->meleeAttackGoal = new MeleeAttackGoal($this, 1.2, false);
		$this->inventory->getListeners()->add(new CallbackInventoryListener(function(Inventory $inventory, int $slot, Item $oldItem) : void {
			if ($slot !== MobInventory::SLOT_MAIN_HAND) {
				return;
			}

			$this->updateAttackType();
		}, null));
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->updateAttackType();
		$this->updatePickupItemsGoal($this->canPickupItems());
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	protected function getAttackInterval() : int{
		return $this->getWorld()->getDifficulty() === World::DIFFICULTY_HARD ? self::HARD_ATTACK_INTERVAL : self::REGULAR_ATTACK_INTERVAL;
	}

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$this->initEntityPickupTrait($nbt);

		if ($nbt->count() === 0) { // First spawn
			// Halloween pumpkin head chance, this is a java only feature, but why not?
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
		return parent::saveNBT()->merge($this->saveNBTPickupTrait());
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(20);
		$this->setAttackDamage(2);
	}

	protected function updatePickupItemsGoal(bool $enabled) : void{
		if ($enabled) {
			$this->goalSelector->addGoal(4, $this->pickupItemsGoal);
		} else {
			$this->goalSelector->removeGoal($this->pickupItemsGoal);
		}
	}

	public function updateAttackType() : void {
		$useMeleeAttackGoal = $this->inventory->getMainHand()->getTypeId() !== ItemTypeIds::BOW;

		$this->goalSelector->removeGoal($useMeleeAttackGoal ? $this->bowAttackGoal : $this->meleeAttackGoal);
		$this->goalSelector->addGoal(0, $useMeleeAttackGoal ? $this->meleeAttackGoal : $this->bowAttackGoal);

		$this->bowAttackGoal->setAttackInterval($this->getAttackInterval());
	}

	public function performRangedAttack(Entity $target, float $force) : void {
		$eyePos = $this->getEyePos();
		$world = $this->getWorld();

		$projectile = new Arrow(Location::fromObject($eyePos, $world), $this, false);
		$projectile->setPickupMode(Arrow::PICKUP_NONE);

		$targetPosition = $target->getPosition();

		$xDiff = $targetPosition->x - $this->location->x;
		$yDiff = ($targetPosition->y + ($target->getSize()->getHeight() / 3)) - $eyePos->y;
		$zDiff = $targetPosition->z - $this->location->z;
		$horizontalDistance = sqrt(($xDiff * $xDiff) + ($zDiff * $zDiff));

		$projectile->setMotion($this->calculateProjectileVelocity(
			$xDiff,
			$yDiff + $horizontalDistance * 0.2,
			$zDiff,
			1.6 * $force,
			14 - $world->getDifficulty() * 4
		));
		$projectile->spawnToAll();

		$world->addSound($eyePos, new BowShootSound());
	}

	/**
	 * Normalizes (x, y, z), applies random per-axis divergence, then rescales to power.
	 */
	private function calculateProjectileVelocity(float $x, float $y, float $z, float $power, float $divergence) : Vector3 {
		$vec = (new Vector3($x, $y, $z))->normalize();

		$random = $this->getRandom();
		return (new Vector3(
			$vec->x + ($random->nextFloat() - $random->nextFloat()) * 0.0075 * $divergence,
			$vec->y + ($random->nextFloat() - $random->nextFloat()) * 0.0075 * $divergence,
			$vec->z + ($random->nextFloat() - $random->nextFloat()) * 0.0075 * $divergence
		))->normalize()->multiply($power);
	}

	//TODO: spawn rules code

	protected function destroyCycles() : void{
		unset(
			$this->bowAttackGoal,
			$this->meleeAttackGoal,
			$this->pickupItemsGoal,
		);
		parent::destroyCycles();
	}
}
