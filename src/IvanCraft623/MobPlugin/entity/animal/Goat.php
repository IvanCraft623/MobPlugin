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

use IvanCraft623\MobPlugin\data\bedrock\GoatVariantIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\goat\JumpToBlockGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\goat\RamAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\genetics\AlleleRange;
use IvanCraft623\MobPlugin\entity\genetics\Gene;
use IvanCraft623\MobPlugin\entity\genetics\Genetic;
use IvanCraft623\MobPlugin\entity\genetics\GeneticTrait;
use IvanCraft623\MobPlugin\entity\genetics\GeneVariant;
use IvanCraft623\MobPlugin\entity\genetics\Genome;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\sound\GoatHornBreakSound;
use IvanCraft623\MobPlugin\sound\GoatMilkSound;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Bucket;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function array_rand;
use function max;

/**
 * Rams whatever comes near, and screams when it is a screamer.
 */
class Goat extends Animal implements Genetic{
	use GeneticTrait {
		initEntity as protected initGenome;
		saveNBT as protected saveGenome;
	}

	private const TAG_VARIANT = "Variant"; //TAG_Int
	private const TAG_HORN_COUNT = "GoatHornCount"; //TAG_Int

	public const LEAP_BOX_SCALE = 0.6; //of its box, while it leaps onto a block

	private const HORN_COUNT = 2; //a goat has two
	private const MIN_ATTACK_COOLDOWN = 30 * 20; //ticks it picks no target for after it starts a ram, at least
	private const MAX_ATTACK_COOLDOWN = 40 * 20; //ticks, at most

	private const MAX_HEALTH = 10; //health
	private const ADULT_ATTACK_DAMAGE = 2; //health
	private const BABY_ATTACK_DAMAGE = 1; //health
	private const FALL_DAMAGE_REDUCTION = 10; //health

	public static function getNetworkTypeId() : string{ return EntityIds::GOAT; }

	protected GoatVariant $variant = GoatVariant::NORMAL;

	private int $attackCooldownEndTick = 0; //server tick

	private int $hornCount = self::HORN_COUNT;

	private ?EntitySizeInfo $normalSize = null; //its size while it leaps, when its box is shrunk

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::GOAT_HEIGHT, VanillaEntitySizes::GOAT_WIDTH);
	}

	public function getName() : string{
		return "Goat";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(self::MAX_HEALTH);
		$this->setAttackDamage(self::ADULT_ATTACK_DAMAGE);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.4;
	}

	public function reachedAgeBoundary() : void{
		parent::reachedAgeBoundary();

		$this->setAttackDamage($this->isBaby() ? self::BABY_ATTACK_DAMAGE : self::ADULT_ATTACK_DAMAGE);
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 1));
		$this->goalSelector->addGoal(3, new BreedGoal($this, 0.6));
		$this->goalSelector->addGoal(4, new TemptGoal($this, 0.75, false));
		$this->goalSelector->addGoal(5, new RamAttackGoal(
			$this,
			runSpeedModifier: 0.7,
			ramSpeedModifier: 1.8,
			minRamDistance: 4,
			ramDistance: 7,
			knockbackForce: 2.5,
			knockbackHeight: 0.04
		));
		$this->goalSelector->addGoal(6, new FollowParentGoal($this, 1));
		$this->goalSelector->addGoal(8, new JumpToBlockGoal(
			$this,
			searchWidth: 10,
			searchHeight: 10,
			minDistance: 1,
			minHeightDifference: 2,
			minPathLength: 8,
			maxVelocity: 1.5,
			minCooldown: 30 * 20,
			maxCooldown: 60 * 20
		));
		$this->goalSelector->addGoal(9, new WaterAvoidingRandomStrollGoal($this, 0.6));
		$this->goalSelector->addGoal(10, new LookAtEntityGoal($this, Player::class, 6, 0.02));
		$this->goalSelector->addGoal(11, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(6, new NearestAttackableGoal(
			entity: $this,
			targetType: Living::class,
			targetValidator: static fn(Living $target) : bool => !$target instanceof Goat,
			condition: fn() => $this->getServer()->getTick() >= $this->attackCooldownEndTick,
			range: 16
		));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->initGenome($nbt);
		$this->hornCount = $nbt->getInt(self::TAG_HORN_COUNT, self::HORN_COUNT);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT()->merge($this->saveGenome());
		$nbt->setInt(self::TAG_VARIANT, GoatVariantIdMap::getInstance()->toId($this->variant));
		$nbt->setInt(self::TAG_HORN_COUNT, $this->hornCount);

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, GoatVariantIdMap::getInstance()->toId($this->variant));
		$properties->setInt(EntityMetadataProperties::GOAT_HORN_COUNT, $this->hornCount);
	}

	public function getVariant() : GoatVariant{
		return $this->variant;
	}

	/**
	 * @return Gene<GoatVariant>
	 */
	public static function getVariantGene() : Gene{
		static $gene = new Gene(
			default: GoatVariant::NORMAL,
			variants: [new GeneVariant(GoatVariant::SCREAMER, main: new AlleleRange(1, 2))],
			minAllele: 1,
			maxAllele: 99,
			mutationRate: 0.02,
			simplified: true
		);

		return $gene;
	}

	/**
	 * @return list<Gene<*>>
	 */
	protected function getGenes() : array{
		return [self::getVariantGene()];
	}

	protected function onGenomeChanged() : void{
		$this->variant = $this->genome->getOutcome(self::getVariantGene());
		$this->networkPropertiesDirty = true;
	}

	/**
	 * Starting a ram keeps the goat from picking another target for a while.
	 */
	public function onRamStart() : void{
		$this->attackCooldownEndTick = $this->getServer()->getTick() + $this->random->nextRange(self::MIN_ATTACK_COOLDOWN, self::MAX_ATTACK_COOLDOWN);
	}

	/**
	 * Shrinks its box while it leaps onto a block, and restores it after.
	 *
	 * @return $this
	 */
	public function setLeaping(bool $leaping) : static{
		$normalSize = $this->normalSize;
		if ($leaping && $normalSize === null) {
			$normalSize = $this->normalSize = $this->getSize();
			$this->setSize(new EntitySizeInfo(
				$normalSize->getHeight() * self::LEAP_BOX_SCALE,
				$normalSize->getWidth() * self::LEAP_BOX_SCALE,
				$normalSize->getEyeHeight() * self::LEAP_BOX_SCALE
			));
		} elseif (!$leaping && $normalSize !== null) {
			$this->setSize($normalSize);
			$this->normalSize = null;
		}

		return $this;
	}

	/**
	 * Drops one of its horns, if it has any. The tune is one of those of its variant.
	 */
	public function dropHorn(Vector3 $position) : void{
		if ($this->hornCount <= 0) {
			return;
		}

		--$this->hornCount;
		$this->networkPropertiesDirty = true;

		$types = $this->variant->getHornTypes();
		$type = $types[array_rand($types)];
		$this->getWorld()->dropItem($position, VanillaItems::GOAT_HORN()->setHornType($type));
		$this->broadcastSound(new GoatHornBreakSound());
	}

	public function attack(EntityDamageEvent $source) : void{
		if ($source->getCause() === EntityDamageEvent::CAUSE_FALL) {
			$damage = max(0, $source->getBaseDamage() - self::FALL_DAMAGE_REDUCTION);
			if ($damage === 0) {
				$source->cancel();
			} else {
				$source->setBaseDamage($damage);
			}
		}

		parent::attack($source);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if (!$this->isBaby() && $player->getInventory()->getItemInHand() instanceof Bucket) {
			Utils::transformItemInHand($player, VanillaItems::MILK_BUCKET());
			$this->broadcastSound(new GoatMilkSound($this->variant));

			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	public function isFood(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::WHEAT;
	}

	public function getBreedOffspring(AgeableMob $partner) : Goat{
		/** @var Goat $partner */
		return (new Goat($this->getLocation()))->setGenome(Genome::inherit($this->genome, $partner->getGenome(), $this->random));
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::GOAT_SPAWN_EGG();
	}
}
