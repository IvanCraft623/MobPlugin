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
use IvanCraft623\MobPlugin\data\bedrock\WolfVariantIdMap;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LeapAtTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\FollowOwnerGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\OwnerHurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\OwnerHurtTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\SitWhenOrderedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tame\TeleportToOwnerGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\tameable\TameableHurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\wolf\BegGoal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingUtils;
use IvanCraft623\MobPlugin\entity\animation\ShakeWetAnimation;
use IvanCraft623\MobPlugin\entity\BodyArmored;
use IvanCraft623\MobPlugin\entity\BodyArmorTrait;
use IvanCraft623\MobPlugin\entity\Collared;
use IvanCraft623\MobPlugin\entity\CollaredTrait;
use IvanCraft623\MobPlugin\entity\Healable;
use IvanCraft623\MobPlugin\entity\HealableTrait;
use IvanCraft623\MobPlugin\entity\monster\skeleton\AbstractSkeleton;
use IvanCraft623\MobPlugin\entity\NeutralMob;
use IvanCraft623\MobPlugin\entity\NeutralMobTrait;
use IvanCraft623\MobPlugin\entity\Pet;
use IvanCraft623\MobPlugin\entity\PetTrait;
use IvanCraft623\MobPlugin\entity\TameableTrait;
use IvanCraft623\MobPlugin\entity\TameByItemTrait;
use IvanCraft623\MobPlugin\item\ExtraItemTypeIds;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\item\WolfArmor;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\sound\WolfArmorSound;
use IvanCraft623\MobPlugin\sound\WolfArmorSoundType;
use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function ceil;
use function max;
use function mt_rand;

class Wolf extends Animal implements Pet, Collared, Healable, BodyArmored, NeutralMob{
	use NeutralMobTrait;
	use TameableTrait, TameByItemTrait, PetTrait, CollaredTrait, HealableTrait, BodyArmorTrait{
		//initEntity()
		TameableTrait::initEntity insteadof PetTrait, CollaredTrait, BodyArmorTrait;
		TameableTrait::initEntity as protected initTameable;
		PetTrait::initEntity as protected initPet;
		CollaredTrait::initEntity as protected initCollared;
		BodyArmorTrait::initEntity as protected initBodyArmor;

		//saveNBT()
		TameableTrait::saveNBT insteadof PetTrait, CollaredTrait, BodyArmorTrait;
		TameableTrait::saveNBT as protected saveTameable;
		PetTrait::saveNBT as protected savePet;
		CollaredTrait::saveNBT as protected saveCollared;
		BodyArmorTrait::saveNBT as protected saveBodyArmor;

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

		//onDeath()
		TameableTrait::onDeath as protected onTameableDeath;

		//sendSpawnPacket()
		BodyArmorTrait::sendSpawnPacket as protected sendBodyArmorSpawnPacket;
	}

	private const TAG_VARIANT = "Variant"; //TAG_Int
	private const TAG_PROPERTIES = "properties"; //TAG_Compound
	private const PROPERTY_INCREASED_MAX_HEALTH = "minecraft:has_increased_max_health"; //TAG_Byte
	private const PROPERTY_ARMORABLE = "minecraft:is_armorable"; //TAG_Byte

	private const ARMOR_REPAIR_DURABILITY = 8; //durability a scute restores

	private const MODIFIER_WOLF_ARMOR = 100; //damage the armor takes, EntityDamageEvent has no modifier for it

	/** The damage that goes through the armor. */
	private const UNABSORBED_CAUSES = [
		EntityDamageEvent::CAUSE_DROWNING => true,
		EntityDamageEvent::CAUSE_SUFFOCATION => true,
		EntityDamageEvent::CAUSE_MAGIC => true,
		EntityDamageEvent::CAUSE_VOID => true,
		EntityDamageEvent::CAUSE_SUICIDE => true,
	];

	private const WILD_MAX_HEALTH = 8; //health
	private const TAMED_MAX_HEALTH = 40; //health
	private const WILD_ATTACK_DAMAGE = 3; //health
	private const TAMED_ATTACK_DAMAGE = 4; //health

	private const ANGER_TICKS = 25 * 20;
	private const TAME_CHANCE = 0.33; //probability per bone
	private const SHAKE_TICKS = 40;

	/** Health restored by each item. */
	private const HEAL_AMOUNTS = [
		ItemTypeIds::RAW_PORKCHOP => 6.0,
		ItemTypeIds::COOKED_PORKCHOP => 16.0,
		ItemTypeIds::RAW_FISH => 4.0,
		ItemTypeIds::RAW_SALMON => 4.0,
		ItemTypeIds::CLOWNFISH => 2.0,
		ItemTypeIds::PUFFERFISH => 2.0,
		ItemTypeIds::COOKED_FISH => 10.0,
		ItemTypeIds::COOKED_SALMON => 12.0,
		ItemTypeIds::RAW_BEEF => 6.0,
		ItemTypeIds::STEAK => 16.0,
		ItemTypeIds::RAW_CHICKEN => 4.0,
		ItemTypeIds::COOKED_CHICKEN => 12.0,
		ItemTypeIds::RAW_MUTTON => 4.0,
		ItemTypeIds::COOKED_MUTTON => 12.0,
		ItemTypeIds::ROTTEN_FLESH => 8.0,
		ItemTypeIds::RAW_RABBIT => 6.0,
		ItemTypeIds::COOKED_RABBIT => 10.0,
		ItemTypeIds::RABBIT_STEW => 20.0,
	];

	/** Meat, which breeds and grows wolves. */
	private const FOOD = [
		ItemTypeIds::RAW_CHICKEN => true,
		ItemTypeIds::COOKED_CHICKEN => true,
		ItemTypeIds::RAW_BEEF => true,
		ItemTypeIds::STEAK => true,
		ItemTypeIds::RAW_MUTTON => true,
		ItemTypeIds::COOKED_MUTTON => true,
		ItemTypeIds::RAW_PORKCHOP => true,
		ItemTypeIds::COOKED_PORKCHOP => true,
		ItemTypeIds::RAW_RABBIT => true,
		ItemTypeIds::COOKED_RABBIT => true,
		ItemTypeIds::ROTTEN_FLESH => true,
	];

	public static function getNetworkTypeId() : string{ return EntityIds::WOLF; }

	protected WolfVariant $variant;

	private bool $begging = false;

	private bool $wet = false;

	private int $shakeTicks = 0; //remaining ticks of the shake

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::WOLF_HEIGHT, VanillaEntitySizes::WOLF_WIDTH);
	}

	public function getName() : string{
		return "Wolf";
	}

	protected function initProperties() : void{
		parent::initProperties();

		if ($this->isTamed()) {
			$this->applyTamedStats();
		} else {
			$this->setMaxHealth(self::WILD_MAX_HEALTH);
			$this->setAttackDamage(self::WILD_ATTACK_DAMAGE);
		}
	}

	public function getDefaultMovementSpeed() : float{
		return 0.3;
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new TeleportToOwnerGoal($this, 12, 24));
		$this->goalSelector->addGoal(2, new class($this, 1.25) extends PanicGoal{
			protected function shouldPanic() : bool{
				//Only fire panics a wolf, not the hits of other mobs
				return $this->entity->isOnFire();
			}
		});
		$this->goalSelector->addGoal(2, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new SitWhenOrderedGoal($this));
		$this->goalSelector->addGoal(4, new LeapAtTargetGoal($this, 0.4));
		$this->goalSelector->addGoal(5, new MeleeAttackGoal($this, 1));
		$this->goalSelector->addGoal(6, new FollowOwnerGoal($this, 1, 10, 2, 12));
		//The behavior pack gives begging 9 and looking at players 6, but in game begging interrupts the look
		$this->goalSelector->addGoal(6, new BegGoal($this, 8));
		$this->goalSelector->addGoal(7, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(8, new WaterAvoidingRandomStrollGoal($this, 1));
		//TODO: Avoid llamas while wild!

		$this->targetSelector->addGoal(1, new OwnerHurtByTargetGoal($this));
		$this->targetSelector->addGoal(2, new OwnerHurtTargetGoal($this));
		$this->targetSelector->addGoal(3, (new TameableHurtByTargetGoal($this))->setAlertOthers());
		$this->targetSelector->addGoal(4, new NearestAttackableGoal(entity: $this, targetType: AbstractSkeleton::class, range: 16));
		$this->targetSelector->addGoal(4, new NearestAttackableGoal(entity: $this, targetType: Sheep::class, condition: fn() => !$this->isTamed(), range: 16));
		$this->targetSelector->addGoal(4, new NearestAttackableGoal(entity: $this, targetType: Rabbit::class, condition: fn() => !$this->isTamed(), range: 16));
		//TODO: Wild wolves also hunt foxes and baby turtles!
	}

	protected function initEntity(CompoundTag $nbt) : void{
		//The stats depend on it, and the health the parent loads on the stats
		$this->initTameable($nbt);

		parent::initEntity($nbt);

		$this->initBodyArmor($nbt);
		$this->initPet($nbt);
		$this->initCollared($nbt);
		if ($this->isOrphaned()) {
			$this->setSitting(true);
		}
		$this->variant = WolfVariantIdMap::getInstance()->fromId($nbt->getInt(self::TAG_VARIANT, -1)) ?? $this->getSpawnVariant();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT()
			->merge($this->saveBodyArmor())
			->merge($this->saveTameable())
			->merge($this->savePet())
			->merge($this->saveCollared());
		$nbt->setInt(self::TAG_VARIANT, WolfVariantIdMap::getInstance()->toId($this->variant));
		$nbt->setTag(self::TAG_PROPERTIES, CompoundTag::create()
			->setByte(self::PROPERTY_INCREASED_MAX_HEALTH, $this->isTamed() ? 1 : 0)
			->setByte(self::PROPERTY_ARMORABLE, $this->canWearArmor() ? 1 : 0)
		);

		return $nbt;
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendBodyArmorSpawnPacket($player);
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
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry());
		$properties->setGenericFlag(EntityMetadataFlags::INTERESTED, $this->begging);
		$properties->setInt(EntityMetadataProperties::VARIANT, WolfVariantIdMap::getInstance()->toId($this->variant));
	}

	private function getSpawnVariant() : WolfVariant{
		$biomeId = $this->getWorld()->getBiomeId($this->location->getFloorX(), $this->location->getFloorY(), $this->location->getFloorZ());
		$tags = BiomeTagMap::getInstance();
		$has = static fn(string $tag) : bool => $tags->hasTag($biomeId, $tag);

		return match(true){
			$has("taiga") && $has("cold") => WolfVariant::ASHEN,
			$has("taiga") && $has("mega") && $has("forest") => $has("mutated") ? WolfVariant::CHESTNUT : WolfVariant::BLACK,
			$has("taiga") && !$has("mega") && !$has("mutated") => WolfVariant::PALE,
			$has("jungle") => WolfVariant::RUSTY,
			$has("grove") => WolfVariant::SNOWY,
			$has("savanna") => WolfVariant::SPOTTED,
			$has("mesa") => WolfVariant::STRIPED,
			$has("forest") && !$has("birch") && !$has("taiga") && !$has("roofed") && !$has("mutated") &&
				!$has("mountain") && !$has("dappled_forest") => WolfVariant::WOODS,
			default => WolfVariant::PALE
		};
	}

	public function getVariant() : WolfVariant{
		return $this->variant;
	}

	/**
	 * @return $this
	 */
	public function setVariant(WolfVariant $variant) : self{
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function isBegging() : bool{
		return $this->begging;
	}

	public function setBegging(bool $begging) : void{
		if ($this->begging !== $begging) {
			$this->begging = $begging;
			$this->networkPropertiesDirty = true;
		}
	}

	public function isBegItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::BONE || $this->isFood($item);
	}

	protected function onTamed() : void{
		if ($this->isOrphaned()) {
			$this->setSitting(true);
		}
		$this->applyTamedStats();
		$this->setHealth($this->getMaxHealth());
		$this->stopBeingAngry();
	}

	private function applyTamedStats() : void{
		$this->setMaxHealth(self::TAMED_MAX_HEALTH);
		$this->setAttackDamage(self::TAMED_ATTACK_DAMAGE);
	}

	protected function isTameItem(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::BONE;
	}

	protected function getTameChance() : float{
		return self::TAME_CHANCE;
	}

	public function setHealth(float $amount) : void{
		parent::setHealth($amount);

		//A tamed wolf's tail shows its health
		if ($this->isTamed()) {
			$this->syncHealthToViewers();
		}
	}

	public function getHealAmount(Item $item) : float{
		return self::HEAL_AMOUNTS[$item->getTypeId()] ?? 0.0;
	}

	public function isFood(Item $item) : bool{
		return isset(self::FOOD[$item->getTypeId()]);
	}

	public function canFallInLove() : bool{
		return $this->isTamed() && $this->getHealth() >= $this->getMaxHealth() && parent::canFallInLove();
	}

	/**
	 * A sitting wolf can be in love, but neither partner mates until it stands.
	 */
	public function canMate(Animal $other) : bool{
		return parent::canMate($other) && !$this->isSitting() && !($other instanceof Pet && $other->isSitting());
	}

	public function canAttack(Entity $target) : bool{
		if (TargetingUtils::isSameTeam($this, $target)) {
			return false;
		}

		return parent::canAttack($target);
	}

	public function startAngerTimer() : void{
		//A tamed wolf is never angry
		if (!$this->isTamed()) {
			$this->setRemainingAngerTicks(self::ANGER_TICKS);
		}
	}

	protected function tickState() : void{
		parent::tickState();

		$this->updateAnger(true);
		$this->tickWetness();
	}

	private function tickWetness() : void{
		if (Utils::isWaterAt($this->getWorld(), $this->location->getFloorX(), $this->location->getFloorY(), $this->location->getFloorZ())) {
			$this->wet = true;
			if ($this->shakeTicks > 0) {
				$this->shakeTicks = 0;
				$this->broadcastAnimation(new ShakeWetAnimation($this, false));
			}
		} elseif ($this->wet) {
			$this->wet = false;
			$this->shakeTicks = self::SHAKE_TICKS;
			$this->broadcastAnimation(new ShakeWetAnimation($this, true));
		} elseif ($this->shakeTicks > 0 && --$this->shakeTicks === 0) {
			$this->broadcastAnimation(new ShakeWetAnimation($this, false));
		}
	}

	/**
	 * Whether the wolf can have armor on its body: only a tamed adult.
	 */
	public function canWearArmor() : bool{
		return $this->isTamed() && !$this->isBaby();
	}

	/**
	 * The armor takes all the damage it can until it breaks, so the wolf is not hurt.
	 */
	public function applyDamageModifiers(EntityDamageEvent $source) : void{
		parent::applyDamageModifiers($source);

		if ($this->getBodyArmor() instanceof WolfArmor && !isset(self::UNABSORBED_CAUSES[$source->getCause()])) {
			$source->setModifier(-$source->getFinalDamage(), self::MODIFIER_WOLF_ARMOR);
		}
	}

	protected function applyPostDamageEffects(EntityDamageEvent $source) : void{
		parent::applyPostDamageEffects($source);

		$armor = $this->getBodyArmor();
		$absorbed = -$source->getModifier(self::MODIFIER_WOLF_ARMOR);
		if (!$armor instanceof WolfArmor || $absorbed <= 0) {
			return;
		}

		$crackiness = $armor->getCrackiness();
		$armor->applyDamage((int) ceil($absorbed));
		if ($armor->isBroken()) {
			$this->setBodyArmor(VanillaItems::AIR());
			$this->broadcastSound(new WolfArmorSound(WolfArmorSoundType::BREAK));

			return;
		}

		$this->broadcastSound(new WolfArmorSound($armor->getCrackiness() !== $crackiness ? WolfArmorSoundType::CRACK : WolfArmorSoundType::DAMAGE));
		$this->setBodyArmor($armor);
	}

	/**
	 * Puts on, takes off or repairs the armor with the item in hand.
	 *
	 * @return bool whether the player's action was used
	 */
	private function interactArmor(Player $player) : bool{
		if ($player->isSneaking() || !$this->canWearArmor()) {
			return false;
		}

		$item = $player->getInventory()->getItemInHand();
		$typeId = $item->getTypeId();
		if ($item instanceof WolfArmor) {
			if (!$this->getBodyArmor()->isNull()) {
				return false;
			}

			Utils::popItemInHand($player);
			$this->setBodyArmor($item);
			$this->broadcastSound(new WolfArmorSound(WolfArmorSoundType::EQUIP));

			return true;
		}

		if ($typeId === ItemTypeIds::SHEARS && !$this->getBodyArmor()->isNull()) {
			$this->getWorld()->dropItem($this->location->add(0, $this->size->getHeight() / 2, 0), $this->getBodyArmor());
			$this->setBodyArmor(VanillaItems::AIR());
			Utils::damageItemInHand($player);
			$this->broadcastSound(new WolfArmorSound(WolfArmorSoundType::UNEQUIP));

			return true;
		}

		$armor = $this->getBodyArmor();
		if ($typeId === ExtraItemTypeIds::ARMADILLO_SCUTE() && $this->isSitting() && $armor instanceof WolfArmor && $armor->getDamage() > 0) {
			$armor->setDamage(max($armor->getDamage() - self::ARMOR_REPAIR_DURABILITY, 0));
			$this->setBodyArmor($armor);
			Utils::popItemInHand($player);
			$player->broadcastAnimation(new ArmSwingAnimation($player));
			$this->broadcastSound(new WolfArmorSound(WolfArmorSoundType::REPAIR));

			return true;
		}

		return false;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$isOwner = $this->isTamed() && $this->getOwningEntityId() === $player->getId();

		return (!$this->isAngry() && $this->interactHealable($player, $clickPos)) ||
			parent::onInteract($player, $clickPos) ||
			$this->interactTameByItem($player, $clickPos) ||
			($isOwner && ($this->interactArmor($player) || $this->interactCollared($player, $clickPos))) ||
			(($isOwner || $this->isOrphaned()) && $this->interactSit($player, $clickPos));
	}

	/**
	 * Whoever sits an orphaned wolf adopts it.
	 */
	private function interactSit(Player $player, Vector3 $clickPos) : bool{
		if ($this->isOrphaned()) {
			$this->setOwningEntity($player);
		}

		return $this->interactPet($player, $clickPos);
	}

	public function getBreedOffspring(AgeableMob $partner) : Wolf{
		/** @var Wolf $partner */
		$offspring = new Wolf($this->getLocation());
		if ($this->isTamed()) {
			$offspring->becomeTamed($this->getOwningEntity());
		}
		$offspring->setVariant(mt_rand(0, 1) === 0 ? $this->variant : $partner->variant);

		return $offspring;
	}

	public function getDrops() : array{
		if ($this->isBaby()) {
			return [];
		}

		$drops = $this->generateDeathLoot(VanillaLootTables::WOLF());
		if (!$this->getBodyArmor()->isNull()) {
			$drops[] = $this->getBodyArmor();
		}

		return $drops;
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::WOLF_SPAWN_EGG();
	}
}
