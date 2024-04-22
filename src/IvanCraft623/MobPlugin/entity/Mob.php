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

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\entity\ai\control\JumpControl;
use IvanCraft623\MobPlugin\entity\ai\control\LookControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\goal\GoalSelector;
use IvanCraft623\MobPlugin\entity\ai\navigation\GroundPathNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\ai\sensing\Sensing;
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;
use IvanCraft623\MobPlugin\sound\MobWarningSound;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Attribute;

use pocketmine\entity\AttributeFactory;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\MeleeWeaponEnchantment;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\Releasable;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\sound\ItemBreakSound;
use pocketmine\world\World;
use function assert;
use function count;
use function lcg_value;
use function max;

abstract class Mob extends Living {
	//TODO!

	private const TAG_PERSISTENT = "Persistent"; //TAG_Byte

	protected PathNavigation $navigation;

	protected LookControl $lookControl;

	protected MoveControl $moveControl;

	protected JumpControl $jumpControl;

	protected GoalSelector $goalSelector;

	protected GoalSelector $targetSelector;

	protected Sensing $sensing;

	/** @var array<int, float> BlockPathTypes->id => malus */
	protected array $pathfindingMalus = [];

	protected float $forwardSpeed = 0;

	protected float $upwardSpeed = 0;

	protected float $sidewaysSpeed = 0;

	protected Vector3 $restrictCenter;

	protected float $restrictRadius = -1;

	protected bool $aggressive = false;

	protected bool $isPersistent = false;

	protected Attribute $attackDamageAttr;

	protected Attribute $attackKnockbackAttr;

	protected Attribute $followRangeAttr;

	protected function initEntity(CompoundTag $nbt) : void{
		$this->initProperties();

		parent::initEntity($nbt);

		$this->isPersistent = $nbt->getByte(self::TAG_PERSISTENT, 0) !== 0;

		$this->goalSelector = new GoalSelector();
		$this->targetSelector = new GoalSelector();
		$this->lookControl = new LookControl($this);
		$this->moveControl = new MoveControl($this);
		$this->jumpControl = new JumpControl($this);
		$this->navigation = $this->createNavigation();
		$this->sensing = new Sensing($this);

		$this->registerGoals();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setByte(self::TAG_PERSISTENT, $this->isPersistent ? 1 : 0);

		return $nbt;
	}

	protected function initProperties() : void{
		$this->setMovementSpeed($this->getDefaultMovementSpeed());
	}

	protected function registerGoals() : void{
	}

	protected function addAttributes() : void{
		parent::addAttributes();

		$this->attributeMap->add($this->attackDamageAttr = AttributeFactory::getInstance()->mustGet(Attribute::ATTACK_DAMAGE));
		$this->attributeMap->add($this->attackKnockbackAttr = AttributeFactory::getInstance()->mustGet(CustomAttributes::ATTACK_KNOCKBACK));

		$this->followRangeAttr = $this->attributeMap->get(Attribute::FOLLOW_RANGE) ?? throw new AssumptionFailedError("Follow range attribute is null");
		;
	}

	public function createNavigation() : PathNavigation{
		return new GroundPathNavigation($this);
	}

	public function getLookControl() : LookControl {
		return $this->lookControl;
	}

	public function getMoveControl() : MoveControl {
		return $this->moveControl;
	}

	public function getJumpControl() : JumpControl {
		return $this->jumpControl;
	}

	public function getNavigation() : PathNavigation{
		return $this->navigation;
	}

	public function getSensing() : Sensing {
		return $this->sensing;
	}

	public function getMobType() : MobType{
		return MobType::UNDEFINED();
	}

	public function getMobCategory() : MobCategory{
		return MobCategory::CREATURE();
	}

	public function setForwardSpeed(float $forwardSpeed) : void {
		$this->forwardSpeed = $forwardSpeed;
	}

	public function setUpwardSpeed(float $upwardSpeed) : void {
		$this->upwardSpeed = $upwardSpeed;
	}

	public function setSidewaysSpeed(float $sidewaysSpeed) : void {
		$this->sidewaysSpeed = $sidewaysSpeed;
	}

	public function getMaxPitchRot() : int {
		return 40;
	}

	public function getMaxYawRot() : int {
		return 75;
	}

	public function getRotSpeed() : int {
		return 10;
	}

	public function getAmbientSoundInterval() : float{
		return 8;
	}

	public function getAmbientSoundIntervalRange() : float{
		return 16;
	}

	public function getLifeTime() : int{
		return $this->ticksLived;
	}

	/**
	 * Returns maximun time in ticks that this entity can live or -1 if undefined.
	 */
	public function getMaxLifeTime() : int{
		return -1;
	}

	public function getAttackDamage() : float{
		return $this->attackDamageAttr->getValue();
	}

	public function setAttackDamage(float $damage) : void{
		$this->attackDamageAttr->setValue($damage);
	}

	public function getAttackKnockback() : float{
		return $this->attackKnockbackAttr->getValue();
	}

	public function setAttackKnockback(float $kb) : void{
		$this->attackKnockbackAttr->setValue($kb);
	}

	public function getFollowRange() : float{
		return $this->followRangeAttr->getValue();
	}

	public function setFollowRange(float $range) : void{
		$this->followRangeAttr->setValue($range);
	}
	public function getMaxFallDistance() : int{
		$defaultMax = parent::getMaxFallDistance();
		if ($this->targetId === null) {
			return $defaultMax;
		}

		$maxFallDistance = (int) ($this->getHealth() - $this->getMaxHealth() / 3);
		$maxFallDistance -= (3 - $this->getWorld()->getDifficulty()) * 4;

		return max(0, $maxFallDistance + $defaultMax);
	}

	/**
	 * Sets whether this entity is forced to not despawn naturally.
	 *
	 * @return $this
	 */
	public function setPersistent(bool $value = true) : self{
		$this->isPersistent = $value;

		return $this;
	}

	/**
	 * Returns whether this entity is forced to not despawn naturally.
	 */
	public function isPersistent() : bool{
		return $this->isPersistent;
	}

	/**
	 * Returns whether this entity cannot despawn naturally.
	 */
	public function isPersistenceRequired() : bool{
		//TODO: check if is passenger
		return $this->isPersistent() || $this->getNameTag() !== "";
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		CustomTimings::$entityAiTick->startTiming();
		$this->tickAi();
		CustomTimings::$entityAiTick->stopTiming();

		$hasUpdate = parent::entityBaseTick($tickDiff);

		$this->checkDespawn();

		//TODO: leash check

		/*if ($this->ticksLived % 5 === 0) {
			$this->updateControlFlags();
			$hasUpdate = true;
		}*/

		return $hasUpdate;
	}

	public function checkDespawn() : void{
		if ($this->getWorld()->getDifficulty() === World::DIFFICULTY_PEACEFUL && $this->shouldDespawnInPeaceful()) {
			$this->flagForDespawn();
			return;
		}

		if (!$this->isPersistenceRequired()) {
			$maxLifetime = $this->getMaxLifeTime();
			if ($maxLifetime !== -1 && $this->ticksLived >= $maxLifetime) {
				$this->flagForDespawn();
				return;
			}

			$nearestPlayer = Utils::getNearestPlayer($this);
			if ($nearestPlayer !== null) {
				$mobCategory = $this->getMobCategory();
				$distanceSquared = $this->location->distanceSquared($nearestPlayer->getPosition());
				if ($this->shouldDespawnWhenFarAway($distanceSquared) &&
					$distanceSquared > $mobCategory->getDespawnDistance() ** 2
				) {
					$this->flagForDespawn();
				}

				$noDespawnSquared = $mobCategory->getNoDespawnDistance() ** 2;
				if ($this->noActionTime > 600 &&
					$this->random->nextBoundedInt(800) === 0 &&
					$distanceSquared > $noDespawnSquared &&
					$this->shouldDespawnWhenFarAway($distanceSquared)
				) {
					$this->flagForDespawn();
				} elseif ($distanceSquared < $noDespawnSquared) {
					$this->noActionTime = 0;
				}
			}
		} else {
			$this->noActionTime = 0;
		}
	}

	public function shouldDespawnInPeaceful() : bool{
		return false;
	}

	public function shouldDespawnWhenFarAway(float $distanceSquared) : bool{
		return true;
	}

	public function tickAi() : void{
		$this->noActionTime++;

		$this->sensing->tick();

		CustomTimings::$goalSelector->startTiming();

		$n = $this->ticksLived + $this->getId();
		if ($n % 2 !== 0 && !$this->justCreated) {
			$this->targetSelector->tickRunningGoals(false);
			$this->goalSelector->tickRunningGoals(false);
		} else {
			$this->targetSelector->tick();
			$this->goalSelector->tick();
		}

		CustomTimings::$goalSelector->stopTiming();

		CustomTimings::$navigation->startTiming();
		$this->navigation->tick();
		CustomTimings::$navigation->stopTiming();

		$this->moveControl->tick();
		$this->lookControl->tick();
		$this->jumpControl->tick();

		// Movement update
		$this->sidewaysSpeed *= 0.98;
		$this->forwardSpeed *= 0.98;
		$this->travel(new Vector3($this->sidewaysSpeed, $this->upwardSpeed, $this->forwardSpeed));
	}

	public function travel(Vector3 $movementInput) : void{
		// TODO: More complex movement suff :P
		$motion = Utils::movementInputToMotion($movementInput, $this->location->yaw, $this->getMovementSpeed());

		//Climb stuff
		if ($this->isCollidedHorizontally && $this->onClimbable()) {
			$motion->y = 0.2 - $this->motion->y;
		}

		$this->addMotion($motion->x, $motion->y, $motion->z);
	}

	protected function onClimbable() : bool{
		return false;
	}

	protected function updateControlFlags() : void{
		// TODO!
	}

	public function getPathfindingMalus(BlockPathTypes $pathType) : float{
		// TODO: vehicle checks
		return $this->pathfindingMalus[$pathType->id()] ?? $pathType->getMalus();
	}

	public function setPathfindingMalus(BlockPathTypes $pathType, float $malus) : void{
		$this->pathfindingMalus[$pathType->id()] = $malus;
	}

	public function canUseReleasable(Releasable $item) : bool{
		return false;
	}

	public function isWithinRestriction(?Vector3 $pos = null) : bool{
		$pos = $pos ?? $this->location;
		if ($this->restrictRadius === -1.0) {
			return true;
		}
		return $this->restrictCenter->distanceSquared($pos) < ($this->restrictRadius ** 2);
	}

	public function restrictTo(Vector3 $center, float $radius) : void{
		$this->restrictCenter = $center;
		$this->restrictRadius = $radius;
	}

	public function getRestrictCenter() : Vector3{
		if (!isset($this->restrictCenter)) {
			$this->restrictCenter = Vector3::zero();
		}
		return $this->restrictCenter;
	}

	public function getRestrictRadius() : float{
		return $this->restrictRadius;
	}

	public function clearRestriction() : void{
		$this->restrictRadius = -1;
	}

	public function hasRestriction() : bool{
		return $this->restrictRadius !== -1.0;
	}

	public function isAggressive() : bool{
		return $this->aggressive;
	}

	public function setAggressive(bool $aggressive = true) : void{
		$this->aggressive = $aggressive;
	}

	public function getPerceivedDistanceSqrForMeleeAttack(Entity $target) : float{
		//TODO: Camels Y extra!
		return $this->location->distanceSquared($target->getPosition());
	}

	protected function doAttackAnimation() : void{
		$this->broadcastAnimation(new ArmSwingAnimation($this));
	}

	/**
	 * Attacks the given entity with the currently-held item.
	 * TODO: make a PR that implements this un PM core.
	 *
	 * @return bool if the entity was dealt damage
	 */
	public function attackEntity(Entity $entity) : bool{
		if(!$entity->isAlive()){
			return false;
		}

		$heldItem = $this->inventory->getItemInHand();
		$oldItem = clone $heldItem;

		$itemAttackPoints = $heldItem->getAttackPoints();
		$attackPoints = $this->getAttackDamage();
		$baseDamage = $itemAttackPoints <= 1 ? $attackPoints : $attackPoints + $itemAttackPoints;

		$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $baseDamage);
		$ev->setKnockBack($this->getAttackKnockback());

		$meleeEnchantmentDamage = 0;
		/** @var EnchantmentInstance[] $meleeEnchantments */
		$meleeEnchantments = [];
		foreach($heldItem->getEnchantments() as $enchantment){
			$type = $enchantment->getType();
			if($type instanceof MeleeWeaponEnchantment && $type->isApplicableTo($entity)){
				$meleeEnchantmentDamage += $type->getDamageBonus($enchantment->getLevel());
				$meleeEnchantments[] = $enchantment;
			}
		}
		$ev->setModifier($meleeEnchantmentDamage, EntityDamageEvent::MODIFIER_WEAPON_ENCHANTMENTS);

		$entity->attack($ev);

		$this->doAttackAnimation();

		if ($this->isOnFire()) {
			$entity->setOnFire(8);
		}

		foreach($meleeEnchantments as $enchantment){
			$type = $enchantment->getType();
			assert($type instanceof MeleeWeaponEnchantment);
			$type->onPostAttack($this, $entity, $enchantment->getLevel());
		}

		if($this->isAlive()){
			//reactive damage like thorns might cause us to be killed by attacking another mob, which
			//would mean we'd already have dropped the inventory by the time we reached here

			$returnedItems = []; //TODO: do something with returned items
			if($heldItem->onAttackEntity($entity, $returnedItems) && $oldItem->equalsExact($this->inventory->getItemInHand())){
				if($heldItem instanceof Durable && $heldItem->isBroken()){
					$this->broadcastSound(new ItemBreakSound());
				}
				$this->inventory->setItemInHand($heldItem);
			}
		}

		return true;
	}

	public function performRangedAttack(Entity $target, float $force) : void{
	}

	public function canStandAt(Vector3 $pos) : bool{
		$world = $this->getWorld();

		$below = $world->getBlock($pos->down());
		if (!$below->isSolid()) {
			return false;
		}

		$diff = $this->location->subtractVector($pos);
		return count($world->getCollisionBlocks($this->boundingBox->addCoord($diff->x, $diff->y, $diff->z), true)) === 0;
	}

	public function onRandomTeleport(Vector3 $from, Vector3 $to) : void{
	}

	public function setTargetEntity(?Entity $target) : void{
		if ($target !== null && $target->getId() !== $this->targetId) {
			$this->broadcastSound(new MobWarningSound($this));
		}

		parent::setTargetEntity($target);
	}

	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{
		if (parent::teleport($pos, $yaw, $pitch)) {
			$this->navigation->stop();

			return true;
		}

		return false;
	}

	public function getEquipmentDropProbability() : float{
		return 0.25;
	}

	/**
	 * @return Item[]
	 */
	public function getEquipmentDrops() : array{
		$drops = [];

		$dropChance = $this->getEquipmentDropProbability();
		foreach ([$this->inventory, $this->armorInventory] as $inventory) {
			foreach ($inventory->getContents() as $item) {
				if (!$item->hasEnchantment(VanillaEnchantments::VANISHING()) && lcg_value() <= $dropChance) {
					if ($item instanceof Durable) {
						$maxDurability = $item->getMaxDurability();
						$item->setDamage($maxDurability - $this->random->nextBoundedInt(1 + $this->random->nextBoundedInt(max($maxDurability - 3, 1))));
					}
					$drops[] = $item;
				}
			}
		}

		return $drops;
	}

	public function getDrops() : array {
		return $this->getEquipmentDrops();
	}

	public function onEat() : void{
	}

	public function onLightningBoltHit() : bool{
		return false;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setFloat(EntityMetadataProperties::AMBIENT_SOUND_INTERVAL_MIN, $this->getAmbientSoundInterval());
		$properties->setFloat(EntityMetadataProperties::AMBIENT_SOUND_INTERVAL_RANGE, $this->getAmbientSoundIntervalRange());
		$properties->setString(EntityMetadataProperties::AMBIENT_SOUND_EVENT, "ambient");
	}
}
