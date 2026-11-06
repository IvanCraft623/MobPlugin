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

namespace IvanCraft623\MobPlugin\entity\monster\illager;

use Closure;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\EquipmentTrait;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Villager;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function count;
use function mt_rand;

class Vindicator extends AbstractIllager {
	use EquipmentTrait;

	private const TARGET_RANGE = 12;
	private const JOHNNY_NAME = "Johnny";

	public static function getNetworkTypeId() : string {
		return EntityIds::VINDICATOR;
	}

	public function getName() : string {
		return "Vindicator";
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(
			VanillaEntitySizes::VINDICATOR_HEIGHT,
			VanillaEntitySizes::VINDICATOR_WIDTH,
			VanillaEntitySizes::VINDICATOR_HEIGHT * 0.9
		);
	}

	protected function initProperties() : void {
		parent::initProperties();

		$this->setMaxHealth(24);
		$this->setAttackDamage(8);
		$this->setFollowRange(64);
	}

	public function getDefaultMovementSpeed() : float {
		return 0.35;
	}

	protected function registerGoals() : void {
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(3, new MeleeAttackGoal($this, 1));
		$this->goalSelector->addGoal(9, new WaterAvoidingRandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(10, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(10, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, new HurtByTargetGoal($this, AbstractIllager::class));
		$this->addTargetGoal(Player::class);
		$this->addTargetGoal(IronGolem::class);
		$this->addTargetGoal(SnowGolem::class);
		$this->addTargetGoal(Villager::class, static fn(Living $target) : bool => $target instanceof Villager && !$target->isBaby());
		//Only named ones look for every non-illager
		$this->addTargetGoal(
			Living::class,
			static fn(Living $target) : bool => !$target instanceof AbstractIllager,
			fn() : bool => $this->isJohnny()
		);
	}

	/**
	 * @phpstan-param class-string<Living>   $type
	 * @phpstan-param null|Closure(Living) : bool $validator
	 * @phpstan-param null|Closure() : bool       $condition
	 */
	private function addTargetGoal(string $type, ?Closure $validator = null, ?Closure $condition = null) : void{
		$goal = new NearestAttackableGoal($this, $type, targetValidator: $validator, condition: $condition);
		$goal->getTargetingConditions()->setRange(self::TARGET_RANGE);
		$this->targetSelector->addGoal(2, $goal);
	}

	/**
	 * Naming a vindicator "Johnny" makes it attack everything that isn't an illager.
	 */
	public function isJohnny() : bool {
		return $this->getNameTag() === self::JOHNNY_NAME;
	}

	public function generateEquipment() : void {
		$this->equipFromLootTable(VanillaLootTables::VINDICATOR_GEAR());
	}

	public function getDrops() : array {
		return [...$this->getEquipmentDrops(), ...$this->generateDeathLoot(VanillaLootTables::VINDICATION_ILLAGER())];
	}

	public function getXpDropAmount() : int {
		if ($this->hasBeenDamagedByPlayer()) {
			return 5 + (count($this->getArmorInventory()->getContents()) * mt_rand(1, 3));
		}

		return 0;
	}

	public function getPickedItem() : ?Item {
		return ExtraVanillaItems::VINDICATOR_SPAWN_EGG();
	}
}
