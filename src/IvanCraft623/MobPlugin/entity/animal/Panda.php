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

use Closure;
use IvanCraft623\MobPlugin\data\bedrock\PandaVariantIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\AvoidEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\panda\LayDownGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\panda\RollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\panda\SnackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\panda\SneezeGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PickupItemsGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundAndSitGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\utils\NearestBlockFinder;
use IvanCraft623\MobPlugin\entity\CarriedItemTrait;
use IvanCraft623\MobPlugin\entity\genetics\AlleleRange;
use IvanCraft623\MobPlugin\entity\genetics\Gene;
use IvanCraft623\MobPlugin\entity\genetics\Genetic;
use IvanCraft623\MobPlugin\entity\genetics\GeneticTrait;
use IvanCraft623\MobPlugin\entity\genetics\GeneVariant;
use IvanCraft623\MobPlugin\entity\genetics\Genome;
use IvanCraft623\MobPlugin\entity\ItemCarrier;
use IvanCraft623\MobPlugin\entity\ItemPickupCapable;
use IvanCraft623\MobPlugin\entity\ItemPickupCapableTrait;
use IvanCraft623\MobPlugin\entity\NeutralMob;
use IvanCraft623\MobPlugin\entity\NeutralMobTrait;
use IvanCraft623\MobPlugin\entity\Sittable;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function min;

class Panda extends Animal implements NeutralMob, Genetic, ItemCarrier, ItemPickupCapable, Sittable{
	use NeutralMobTrait;
	use GeneticTrait, CarriedItemTrait, ItemPickupCapableTrait {
		//initEntity()
		GeneticTrait::initEntity insteadof CarriedItemTrait, ItemPickupCapableTrait;
		GeneticTrait::initEntity as protected initGenome;
		CarriedItemTrait::initEntity as protected initCarriedItem;

		//saveNBT()
		GeneticTrait::saveNBT insteadof CarriedItemTrait, ItemPickupCapableTrait;
		GeneticTrait::saveNBT as protected saveGenome;
		CarriedItemTrait::saveNBT as protected saveCarriedItem;

		//sendSpawnPacket()
		CarriedItemTrait::sendSpawnPacket as protected sendCarriedItemSpawnPacket;
	}

	private const TAG_VARIANT = "Variant"; //TAG_Int

	private const ADULT_ANGER_TICKS = 500;
	private const BABY_ANGER_TICKS = 1;
	private const ALERT_RANGE = 41; //blocks
	private const BABY_SCALE = 0.4;
	private const BREEDING_BAMBOO_COUNT = 8; //bamboo blocks around it that it needs to breed
	private const BREEDING_BAMBOO_RADIUS = 5; //blocks

	public static function getNetworkTypeId() : string{ return EntityIds::PANDA; }

	private PandaVariant $variant = PandaVariant::NORMAL;

	private bool $sitting = false;

	private bool $eating = false;

	private bool $layingDown = false;

	private int $snackCooldownEndTick = 0; //server tick before which it won't look for or sit with a snack

	private PickupItemsGoal $pickupItemsGoal;

	private bool $rolling = false;

	private bool $sneezing = false;

	/** @var Goal[] the goals that depend on the variant and age */
	private array $variantGoals = [];

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::PANDA_HEIGHT, VanillaEntitySizes::PANDA_WIDTH);
	}

	public function getName() : string{
		return "Panda";
	}

	public function getDefaultMovementSpeed() : float{
		return $this->variant->getMovementSpeed();
	}

	public function getBabyScale() : float{
		return self::BABY_SCALE;
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		//Runs away, faster the lazier it is. An aggressive panda only runs from what burns it
		$this->goalSelector->addGoal(1, new class($this) extends PanicGoal{
			public function __construct(private Panda $panda){
				parent::__construct($panda, $panda->getVariant()->getPanicSpeedModifier());
			}

			protected function shouldPanic() : bool{
				return $this->panda->getVariant() === PandaVariant::AGGRESSIVE ? $this->panda->isOnFire() : parent::shouldPanic();
			}

			public function start() : void{
				$this->speedModifier = $this->panda->getVariant()->getPanicSpeedModifier();
				parent::start();
			}
		});
		//Only adults fight, a cub just runs
		$this->goalSelector->addGoal(2, new class($this) extends MeleeAttackGoal{
			public function __construct(private Panda $panda){
				parent::__construct($panda, 1);
			}

			public function canUse() : bool{
				return !$this->panda->isBaby() && $this->panda->isIdle() && parent::canUse();
			}

			public function canContinueToUse() : bool{
				return !$this->panda->isBaby() && parent::canContinueToUse();
			}
		});
		$this->pickupItemsGoal = new class($this) extends PickupItemsGoal{
			public function __construct(private Panda $panda){
				parent::__construct($panda, 1.2, 1.0, 8, replaceItems: false);
				$this->setWantedItems(Panda::getWantedItems());
			}

			public function canUse() : bool{
				return $this->panda->canSnack() && parent::canUse();
			}
		};
		$this->goalSelector->addGoal(3, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(4, new TemptGoal($this, 1.25, false));
		$this->goalSelector->addGoal(8, new LookAtEntityGoal($this, Player::class, 6, 0.02));
		$this->goalSelector->addGoal(9, new RandomLookAroundGoal($this));
		$this->goalSelector->addGoal(13, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(14, new WaterAvoidingRandomStrollGoal($this, 0.8));

		$this->targetSelector->addGoal(1, new HurtByTargetGoal($this));
		$this->registerVariantGoals();
		//TODO: Baby pandas can ride zombies
	}

	/**
	 * What each variant, and a cub, does on top of the rest.
	 */
	private function registerVariantGoals() : void{
		foreach ($this->variantGoals as $goal) {
			$this->goalSelector->removeGoal($goal);
		}
		$this->variantGoals = [];

		$lazy = $this->variant === PandaVariant::LAZY;
		$this->addVariantGoal($lazy ? 3 : 2, new SnackGoal(
			$this,
			cooldown: $this->variant->getSnackCooldown(),
			minCooldown: $this->variant->getSnackMinCooldown(),
			stopChance: $this->variant->getSnackStopChance()
		));
		$this->addVariantGoal($lazy ? 6 : 5, new RandomLookAroundAndSitGoal(
			$this,
			minLookCount: $this->variant->getLookAndSitMinLookCount(),
			maxLookCount: $this->variant->getLookAndSitMaxLookCount(),
			minLookTime: 80,
			maxLookTime: 100,
			probability: $this->variant->getLookAndSitProbability(),
			cooldown: $this->variant->getLookAndSitCooldown()
		));

		switch ($this->variant) {
			case PandaVariant::LAZY:
				$this->addVariantGoal(5, new LayDownGoal($this, startInterval: 400, stopInterval: 2000));
				break;
			case PandaVariant::PLAYFUL:
				$this->addVariantGoal(12, new RollGoal($this, probability: $this->variant->getRollProbability()));
				break;
			case PandaVariant::WORRIED:
				//Keeps away from everything but other pandas
				$this->addVariantGoal(5, new class($this) extends AvoidEntityGoal{
					public function __construct(Panda $panda){
						parent::__construct($panda, [Living::class], maxDistance: 16, walkSpeedModifier: 1, sprintSpeedModifier: 1.5);
					}

					protected function isAvoided(Living $candidate) : bool{
						return !$candidate instanceof Panda;
					}
				});
				//TODO: Worried pandas get scared and sit down in thunderstorms, needs weather
				break;
		}

		if ($this->isBaby()) {
			if ($this->variant !== PandaVariant::PLAYFUL) {
				$this->addVariantGoal(12, new RollGoal($this, probability: $this->variant->getRollProbability()));
			}
			$this->addVariantGoal(7, new SneezeGoal($this, probability: $this->variant->getSneezeProbability()));
		}
	}

	private function addVariantGoal(int $priority, Goal $goal) : void{
		$this->goalSelector->addGoal($priority, $goal);
		$this->variantGoals[] = $goal;
	}

	public function reachedAgeBoundary() : void{
		parent::reachedAgeBoundary();

		$this->registerVariantGoals();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->initCarriedItem($nbt);

		$this->initGenome($nbt);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT()->merge($this->saveGenome());
		$nbt->setInt(self::TAG_VARIANT, PandaVariantIdMap::getInstance()->toId($this->variant));

		return $nbt->merge($this->saveCarriedItem());
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, PandaVariantIdMap::getInstance()->toId($this->variant));
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setFloat(EntityMetadataProperties::SITTING_AMOUNT, $this->sitting ? 1.0 : 0.0);
		$properties->setFloat(EntityMetadataProperties::SITTING_AMOUNT_PREVIOUS, $this->sitting ? 1.0 : 0.0);
		$properties->setGenericFlag(EntityMetadataFlags::EATING, $this->isEating());
		$properties->setGenericFlag(EntityMetadataFlags::LAYING_DOWN, $this->layingDown);
		$properties->setFloat(EntityMetadataProperties::LAYING_AMOUNT, $this->layingDown ? 1.0 : 0.0);
		$properties->setFloat(EntityMetadataProperties::LAYING_AMOUNT_PREVIOUS, $this->layingDown ? 1.0 : 0.0);
		$properties->setGenericFlag(EntityMetadataFlags::ROLLING, $this->rolling);
		$properties->setGenericFlag(EntityMetadataFlags::SNEEZING, $this->sneezing);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry());
	}

	/**
	 * @return Gene<PandaVariant>
	 */
	public static function getVariantGene() : Gene{
		static $gene = new Gene(
			default: PandaVariant::NORMAL,
			variants: [
				new GeneVariant(PandaVariant::LAZY, main: new AlleleRange(0, 0)),
				new GeneVariant(PandaVariant::WORRIED, main: new AlleleRange(1, 1)),
				new GeneVariant(PandaVariant::PLAYFUL, main: new AlleleRange(2, 2)),
				new GeneVariant(PandaVariant::AGGRESSIVE, main: new AlleleRange(3, 3)),
				new GeneVariant(PandaVariant::WEAK, both: new AlleleRange(4, 7)),
				new GeneVariant(PandaVariant::BROWN, both: new AlleleRange(8, 9))
			],
			minAllele: 0,
			maxAllele: 14,
			mutationRate: 0.03125
		);

		return $gene;
	}

	/**
	 * @return list<Gene<*>>
	 */
	protected function getGenes() : array{
		return [self::getVariantGene()];
	}

	/**
	 * Takes on the variant that the genes make and its stats.
	 */
	protected function onGenomeChanged() : void{
		$this->variant = $this->genome->getOutcome(self::getVariantGene());

		$this->setMaxHealth($this->variant->getMaxHealth());
		$this->setHealth(min($this->getHealth(), $this->getMaxHealth()));
		$this->setAttackDamage($this->variant->getAttackDamage());
		$this->moveSpeedAttr->setDefaultValue($this->variant->getMovementSpeed());
		$this->moveSpeedAttr->setValue($this->variant->getMovementSpeed());
		$this->networkPropertiesDirty = true;
		$this->registerVariantGoals();
	}

	public function getVariant() : PandaVariant{
		return $this->variant;
	}

	public function isSitting() : bool{
		return $this->sitting;
	}

	public function setSitting(bool $sitting) : static{
		if ($sitting !== $this->sitting) {
			$this->sitting = $sitting;
			$this->networkPropertiesDirty = true;
		}

		return $this;
	}

	public function isEating() : bool{
		return $this->eating;
	}

	public function setEating(bool $eating) : static{
		if ($eating !== $this->eating) {
			$this->eating = $eating;
			$this->networkPropertiesDirty = true;
		}

		return $this;
	}

	/**
	 * Nothing is going on that it would have to stop for.
	 */
	public function isIdle() : bool{
		return !$this->sitting && !$this->isEating() && !$this->layingDown && !$this->rolling;
	}

	public function setLayingDown(bool $layingDown) : static{
		if ($layingDown !== $this->layingDown) {
			$this->layingDown = $layingDown;
			$this->networkPropertiesDirty = true;
		}

		return $this;
	}

	public function setRolling(bool $rolling) : static{
		if ($rolling !== $this->rolling) {
			$this->rolling = $rolling;
			$this->networkPropertiesDirty = true;
		}

		return $this;
	}

	public function setSneezing(bool $sneezing) : static{
		if ($sneezing !== $this->sneezing) {
			$this->sneezing = $sneezing;
			$this->networkPropertiesDirty = true;
		}

		return $this;
	}

	/**
	 * Whether it eats the item, and takes it from players.
	 */
	public function isSnack(Item $item) : bool{
		$typeId = $item->getTypeId();

		return $typeId === ItemTypeIds::BAMBOO || $typeId === ItemTypeIds::fromBlockTypeId(BlockTypeIds::CAKE);
	}

	/**
	 * Whether it is in a state to go for a snack or sit down with one.
	 */
	public function canSnack() : bool{
		return !$this->isBaby() && !$this->isInWater() && $this->isIdle() && $this->getServer()->getTick() >= $this->snackCooldownEndTick;
	}

	/**
	 * @param int $ticks that it won't snack for
	 */
	public function startSnackCooldown(int $ticks) : void{
		$this->snackCooldownEndTick = $this->getServer()->getTick() + $ticks;
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->setCanPickUpItems();
	}

	protected function updatePickupItemsGoal(bool $enabled) : void{
		if ($enabled) {
			$this->goalSelector->addGoal(2, $this->pickupItemsGoal);
		} else {
			$this->goalSelector->removeGoal($this->pickupItemsGoal);
		}
	}

	/**
	 * It takes a bamboo or a cake, to eat sat down.
	 *
	 * @return array<int, int|Closure(Item): int>
	 */
	public static function getWantedItems() : array{
		return [ItemTypeIds::BAMBOO, ItemTypeIds::fromBlockTypeId(BlockTypeIds::CAKE)];
	}

	/**
	 * Any panda falls in love, but a pair only has a cub with a grove of bamboo around them.
	 */
	public function canMate(Animal $other) : bool{
		return parent::canMate($other) && $this->hasEnoughBamboo();
	}

	private function hasEnoughBamboo() : bool{
		$count = 0;
		foreach (NearestBlockFinder::find(
			$this->getWorld(),
			$this->getPosition(),
			self::BREEDING_BAMBOO_RADIUS,
			self::BREEDING_BAMBOO_RADIUS,
			BlockTypeIds::BAMBOO
		) as $_) {
			if (++$count >= self::BREEDING_BAMBOO_COUNT) {
				return true;
			}
		}

		return false;
	}

	public function isFood(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::BAMBOO;
	}

	/**
	 * A player can give it a bamboo or a cake to hold, which calms it down.
	 */
	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if (parent::onInteract($player, $clickPos)) {
			return true;
		}

		$item = $player->getInventory()->getItemInHand();
		if ($this->isSnack($item) && $this->getInventory()->getItemInHand()->isNull()) {
			$this->getInventory()->setItemInHand((clone $item)->setCount(1));
			Utils::popItemInHand($player);
			$this->stopBeingAngry();

			return true;
		}

		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$wasAngry = $this->isAngry();
		$this->updateAnger(true);
		if ($this->isAngry() !== $wasAngry) {
			$this->networkPropertiesDirty = true;
		}

		return $hasUpdate;
	}

	/**
	 * Calls the aggressive pandas around to its target, as a cub or grown up; a cub stays angry for a tick only.
	 */
	public function startAngerTimer() : void{
		$this->setRemainingAngerTicks($this->isBaby() ? self::BABY_ANGER_TICKS : self::ADULT_ANGER_TICKS);

		$target = $this->getTargetEntity();
		if ($target !== null) {
			$this->alertAggressivePandas($target);
		}
	}

	private function alertAggressivePandas(Entity $target) : void{
		foreach ($this->getSensing()->getNearbyLiving(self::ALERT_RANGE, HurtByTargetGoal::ALERT_RANGE_Y) as $other) {
			if (
				$other instanceof Panda &&
				$other->getVariant() === PandaVariant::AGGRESSIVE &&
				!$other->isBaby() &&
				$other->getTargetEntityId() === null
			) {
				$other->setTargetEntity($target);
			}
		}
	}

	/**
	 * Only an aggressive panda keeps fighting, the others stop after one hit.
	 */
	public function attackEntity(Entity $entity) : bool{
		$attacked = parent::attackEntity($entity);
		if ($attacked && $this->variant !== PandaVariant::AGGRESSIVE) {
			$this->setTargetEntity(null);
		}

		return $attacked;
	}

	public function getBreedOffspring(AgeableMob $partner) : Panda{
		/** @var Panda $partner */
		return (new Panda($this->getLocation()))->setGenome(Genome::inherit($this->genome, $partner->getGenome(), $this->random));
	}

	public function getDrops() : array{
		return [
			...$this->isBaby() ? [] : $this->generateDeathLoot(VanillaLootTables::PANDA()),
			...$this->getCarriedItemDrops()
		];
	}

	public function getEquipmentDropProbability(Item $item, Inventory $inventory, int $slot) : float{
		return 1.0;
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendCarriedItemSpawnPacket($player);
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::PANDA_SPAWN_EGG();
	}
}