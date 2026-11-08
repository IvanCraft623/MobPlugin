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

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RangedAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\witch\DrinkPotionGoal;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\entity\RangedAttackMob;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\sound\ThrowSound;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living as PMLiving;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function max;

class Witch extends Monster implements RangedAttackMob {

	private const TARGET_RANGE = 10; //blocks
	private const DRINK_TICKS = 32;
	private const DRINK_SLOWDOWN = 0.25; //movement speed lost while drinking
	private const POTION_SPEED = 0.75; //blocks per tick
	private const POTION_DIVERGENCE = 8; //random spread
	private const SLOWNESS_DISTANCE_SQUARED = 64; //blocks squared
	private const WEAKNESS_DISTANCE_SQUARED = 9; //blocks squared
	private const POISON_MIN_HEALTH = 8; //health points

	public static function getNetworkTypeId() : string {
		return EntityIds::WITCH;
	}

	private ?PotionType $drinking = null;

	private int $drinkTicks = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(
			VanillaEntitySizes::WITCH_HEIGHT,
			VanillaEntitySizes::WITCH_WIDTH,
			VanillaEntitySizes::WITCH_HEIGHT * 0.9
		);
	}

	public function getName() : string {
		return "Witch";
	}

	protected function initProperties() : void {
		parent::initProperties();

		$this->setMaxHealth(26);
		$this->setFollowRange(64);
	}

	public function getDefaultMovementSpeed() : float {
		return 0.25;
	}

	protected function registerGoals() : void {
		$this->goalSelector->addGoal(1, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new DrinkPotionGoal($this));
		$this->goalSelector->addGoal(2, new RangedAttackGoal($this, 1, 60, 60, 10));
		$this->goalSelector->addGoal(4, new RandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(5, new LookAtEntityGoal($this, Player::class, 16));
		$this->goalSelector->addGoal(5, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, new HurtByTargetGoal($this));
		foreach ([Player::class, SnowGolem::class, IronGolem::class] as $type) {
			$this->targetSelector->addGoal(2, new NearestAttackableGoal($this, $type, range: self::TARGET_RANGE));
		}
	}

	public function isDrinking() : bool {
		return $this->drinking !== null;
	}

	public function startDrinking(PotionType $potion) : void {
		$this->drinking = $potion;
		$this->drinkTicks = self::DRINK_TICKS;
		$this->getInventory()->setItemInHand(VanillaItems::POTION()->setType($potion));
		$this->moveSpeedAttr->setValue(max(0.0, $this->getDefaultMovementSpeed() - self::DRINK_SLOWDOWN));
	}

	protected function tickState() : void {
		parent::tickState();

		if ($this->drinking !== null && --$this->drinkTicks <= 0) {
			foreach ($this->drinking->getEffects() as $effect) {
				$this->getEffects()->add($effect);
			}
			$this->drinking = null;
			$this->getInventory()->setItemInHand(VanillaItems::AIR());
			$this->moveSpeedAttr->setValue($this->getDefaultMovementSpeed());
		}
	}

	public function performRangedAttack(Entity $target, float $force) : void {
		//Aims from the feet, at the target's chest
		$aimPoint = $target->getPosition()->withComponents(null, $target->getEyePos()->y - 1.1, null);
		$potion = new SplashPotion(Location::fromObject($this->getEyePos(), $this->getWorld()), $this, $this->choosePotion($target));
		$potion->setMotion(Utils::aimProjectile(
			$this->getRandom(),
			$this->location,
			$aimPoint,
			self::POTION_SPEED,
			self::POTION_DIVERGENCE
		));
		$potion->spawnToAll();

		$this->broadcastSound(new ThrowSound($this));
		$this->doAttackAnimation();
	}

	private function choosePotion(Entity $target) : PotionType {
		$distanceSquared = $this->location->distanceSquared($target->getPosition());
		if ($target instanceof PMLiving) {
			$effects = $target->getEffects();
			if ($distanceSquared >= self::SLOWNESS_DISTANCE_SQUARED && !$effects->has(VanillaEffects::SLOWNESS())) {
				return PotionType::SLOWNESS;
			}
			if ($target->getHealth() >= self::POISON_MIN_HEALTH && !$effects->has(VanillaEffects::POISON())) {
				return PotionType::POISON;
			}
			if ($distanceSquared <= self::WEAKNESS_DISTANCE_SQUARED && !$effects->has(VanillaEffects::WEAKNESS()) &&
				$this->getRandom()->nextFloat() < 0.25
			) {
				return PotionType::WEAKNESS;
			}
		}

		return PotionType::HARMING;
	}

	public function getDrops() : array {
		return $this->generateDeathLoot(VanillaLootTables::WITCH());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::WITCH_SPAWN_EGG();
	}
}
