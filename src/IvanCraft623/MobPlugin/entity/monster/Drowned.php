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

use IvanCraft623\MobPlugin\entity\ai\control\AquaticMoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\SwimThrust;
use IvanCraft623\MobPlugin\entity\ai\goal\drowned\TridentAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FleeSunlightGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\navigation\locomotion\AmphibiousLocomotion;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\animal\Axolotl;
use IvanCraft623\MobPlugin\entity\DoorBreaker;
use IvanCraft623\MobPlugin\entity\DoorBreakerTrait;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\entity\projectile\DrownedTrident;
use IvanCraft623\MobPlugin\entity\RangedAttackMob;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\Villager;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\TridentThrowSound;
use function assert;

class Drowned extends AbstractZombie implements DoorBreaker, RangedAttackMob {
	use DoorBreakerTrait;

	private const TARGET_RANGE = 20; //blocks
	private const RANGED_SPAWN_CHANCE = 16; //one ranged drowned in this many spawns
	private const TRIDENT_SPEED = 1.6; //blocks per tick
	private const TRIDENT_DIVERGENCE = 14; //random spread on peaceful, 4 less per difficulty

	public static function getNetworkTypeId() : string {
		return EntityIds::DROWNED;
	}

	public function getName() : string {
		return "Drowned";
	}

	private bool $swimPose = false;

	protected function tickState() : void{
		parent::tickState();

		$swimPose = $this->getMoveControl()->isSwimming();
		if ($swimPose !== $this->swimPose) {
			$this->swimPose = $swimPose;
			$this->networkPropertiesDirty = true;
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		//Only the animation: Living::setSwimming() would also shrink the hitbox
		$properties->setGenericFlag(EntityMetadataFlags::SWIMMING, $this->swimPose);
	}

	public function isSunSensitive() : bool{
		return true;
	}

	public function getMoveControl() : AquaticMoveControl {
		assert($this->moveControl instanceof AquaticMoveControl);
		return $this->moveControl;
	}

	public function setTargetEntity(?Entity $target) : void {
		parent::setTargetEntity($target);

		//Only hunting drowned swim, the rest wade through the water
		$this->navigation->getRules()->canSwim = $target !== null;
	}

	public function createNavigation() : PathNavigation{
		$navigation = new PathNavigation($this, new AmphibiousLocomotion(thrust: SwimThrust::SLOPED, walksSeabed: true));
		$navigation->getRules()->canSwim = false;

		return $navigation;
	}

	protected function registerBehaviorGoals() : void {
		$this->goalSelector->addGoal(2, new FleeSunlightGoal($this, 1));
		$this->goalSelector->addGoal(2, new TridentAttackGoal($this));
		$this->goalSelector->addGoal(3, new MeleeAttackGoal($this, 1));
		//TODO: Stomp turtle eggs!
		$this->goalSelector->addGoal(7, new RandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(8, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(9, new RandomLookAroundGoal($this));
	}

	protected function registerTargetGoals() : void {
		$this->targetSelector->addGoal(1, new HurtByTargetGoal($this));
		foreach([Player::class, IronGolem::class, SnowGolem::class, Axolotl::class] as $type) {
			$this->addTargetGoal($type, true);
		}
		$this->addTargetGoal(Villager::class, false);

		//TODO: Attack baby turtles!
	}

	/**
	 * @phpstan-param class-string<Living> $type
	 */
	private function addTargetGoal(string $type, bool $mustSee) : void {
		$this->targetSelector->addGoal(2, new NearestAttackableGoal(
			entity: $this,
			targetType: $type,
			mustSee: $mustSee,
			targetValidator: fn(Living $target) : bool => self::isStandingInWater($target) || !Utils::isDaytime($this->getWorld()),
			range: self::TARGET_RANGE
		));
	}

	private static function isStandingInWater(Living $entity) : bool {
		$position = $entity->getPosition();

		return Utils::isWaterAt($position->getWorld(), $position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
	}

	public function isHoldingTrident() : bool {
		return $this->getInventory()->getMainHand()->getTypeId() === ItemTypeIds::TRIDENT;
	}

	public function performRangedAttack(Entity $target, float $force) : void {
		$eyePos = $this->getEyePos();

		$trident = new DrownedTrident(Location::fromObject($eyePos, $this->getWorld()), $this->getInventory()->getMainHand(), $this);
		$trident->setMotion(Utils::aimProjectile(
			$this->getRandom(),
			$eyePos,
			$target->getPosition()->add(0, $target->getSize()->getHeight() / 3, 0),
			self::TRIDENT_SPEED,
			self::TRIDENT_DIVERGENCE - $this->getWorld()->getDifficulty() * 4
		));
		$trident->spawnToAll();

		$this->getWorld()->addSound($eyePos, new TridentThrowSound());
		$this->doAttackAnimation();
	}

	public function generateEquipment() : void{
		$ranged = $this->getRandom()->nextBoundedInt(self::RANGED_SPAWN_CHANCE) === 0;
		$this->equipFromLootTable($ranged ? VanillaLootTables::DROWNED_RANGED_EQUIPMENT() : VanillaLootTables::DROWNED_EQUIPMENT());
	}

	public function getDrops() : array {
		return [...$this->getEquipmentDrops(), ...$this->generateDeathLoot(VanillaLootTables::DROWNED())];
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::DROWNED_SPAWN_EGG();
	}
}
