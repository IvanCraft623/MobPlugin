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
use IvanCraft623\MobPlugin\data\bedrock\FoxVariantIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\AvoidUntrustedEntitiesGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\ConsumeCarriedItemGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\EatableBlock;
use IvanCraft623\MobPlugin\entity\ai\goal\FindCoverGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\fox\DefendTrustedTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\fox\StalkAndPounceGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\NapGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PickupItemsGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RaidGardenGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundAndSitGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\aquatic\AbstractFish;
use IvanCraft623\MobPlugin\entity\aquatic\Pufferfish;
use IvanCraft623\MobPlugin\entity\CarriedItemTrait;
use IvanCraft623\MobPlugin\entity\ItemCarrier;
use IvanCraft623\MobPlugin\entity\ItemPickupCapable;
use IvanCraft623\MobPlugin\entity\ItemPickupCapableTrait;
use IvanCraft623\MobPlugin\entity\Sittable;
use IvanCraft623\MobPlugin\entity\Sleepable;
use IvanCraft623\MobPlugin\entity\SleepableTrait;
use IvanCraft623\MobPlugin\entity\Trusting;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\CaveVines;
use pocketmine\block\SweetBerryBush;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\FoodSourceItem;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function array_keys;
use function array_map;
use function count;
use function mt_rand;

/**
 * Distrusts players until it is born of a pair they bred, then defends them.
 */
class Fox extends Animal implements Trusting, ItemCarrier, ItemPickupCapable, Sittable, Sleepable{
	use CarriedItemTrait, ItemPickupCapableTrait, SleepableTrait {
		//initEntity()
		CarriedItemTrait::initEntity insteadof ItemPickupCapableTrait;
		CarriedItemTrait::initEntity as protected initCarriedItem;

		//saveNBT()
		CarriedItemTrait::saveNBT insteadof ItemPickupCapableTrait;
		CarriedItemTrait::saveNBT as protected saveCarriedItem;

		//sendSpawnPacket()
		CarriedItemTrait::sendSpawnPacket as protected sendCarriedItemSpawnPacket;

		//attackEntity()
		CarriedItemTrait::attackEntity as protected attackEntityWithHeldItem;
	}

	private const TAG_VARIANT = "Variant"; //TAG_Int
	private const TAG_TRUSTED_PLAYERS = "TrustedPlayers"; //TAG_List<TAG_String>, player UUIDs

	private const COMPONENT_GROUP_TRUSTING = "minecraft:trusting_fox";

	private const MAX_HEALTH = 10; //health
	private const ATTACK_DAMAGE = 2; //health

	/** Item types that breed and grow foxes. */
	private const FOOD = [
		ItemTypeIds::SWEET_BERRIES => true,
		ItemTypeIds::GLOW_BERRIES => true,
	];

	public static function getNetworkTypeId() : string{ return EntityIds::FOX; }

	protected FoxVariant $variant;

	/**
	 * @var true[] player UUID => trusted
	 * @phpstan-var array<string, true>
	 */
	private array $trustedPlayers = [];

	private bool $defending = false;

	private bool $immuneToFallDamage = false;

	private bool $sitting = false;

	private PickupItemsGoal $pickupItemsGoal;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::FOX_HEIGHT, VanillaEntitySizes::FOX_WIDTH);
	}

	public function getName() : string{
		return "Fox";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(self::MAX_HEALTH);
		$this->setAttackDamage(self::ATTACK_DAMAGE);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.3;
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, $this->createMeleeAttackGoal(true));
		$this->goalSelector->addGoal(2, new PanicGoal($this, 1.25));
		$this->goalSelector->addGoal(3, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new TemptGoal($this, 0.5, true));
		$this->goalSelector->addGoal(5, new AvoidUntrustedEntitiesGoal($this, 10, 1, 1.5, [PolarBear::class, Wolf::class], true));
		$this->goalSelector->addGoal(7, new StalkAndPounceGoal(
			$this,
			stalkSpeedModifier: 1.2,
			maxStalkDistance: 12,
			leapHeight: 0.9,
			leapDistance: 0.8,
			maxPounceDistance: 5,
			strikeDistance: 2,
			interestTicks: 2 * 20,
			stuckTicks: 2 * 20
		));
		$this->goalSelector->addGoal(8, new NapGoal(
			$this,
			minCooldown: 2 * 20,
			maxCooldown: 7 * 20,
			detectDistance: 12,
			detectHeight: 6,
			canNap: fn() : bool => $this->canNap(),
			isWakeException: fn(Living $other) : bool => $this->staysAsleepFor($other)
		));
		$this->goalSelector->addGoal(9, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(9, new FindCoverGoal(
			$this,
			speedModifier: 1,
			cooldown: 5 * 20,
			condition: fn() : bool => Utils::isDaytime($this->getWorld())
		));
		$this->goalSelector->addGoal(10, $this->createMeleeAttackGoal(false));
		$this->pickupItemsGoal = (new PickupItemsGoal($this, 0.5, 2, 3))->setWantedItems(self::getWantedItems());
		$this->goalSelector->addGoal(12, new RaidGardenGoal($this, 1.2, [
			new EatableBlock(
				BlockTypeIds::SWEET_BERRY_BUSH,
				static fn(SweetBerryBush $bush) : Block => $bush->setAge(SweetBerryBush::STAGE_BUSH_NO_BERRIES),
				static fn(SweetBerryBush $bush) : bool => $bush->getBerryDropAmount() > 0
			),
			new EatableBlock(
				BlockTypeIds::CAVE_VINES,
				static fn(CaveVines $vines) : Block => $vines->setBerries(false), //TODO: not observed in vanilla
				static fn(CaveVines $vines) : bool => $vines->hasBerries()
			)
		], searchRange: 12, searchHeight: 2, goalRadius: 0.8, initialEatDelay: 2));
		$this->goalSelector->addGoal(12, new ConsumeCarriedItemGoal($this, FoodSourceItem::class, delayBeforeConsuming: 28));
		$this->goalSelector->addGoal(12, new RandomLookAroundAndSitGoal(
			$this,
			minLookCount: 2,
			maxLookCount: 5,
			minLookTime: 80,
			maxLookTime: 100,
			probability: 0.001
		));
		$this->goalSelector->addGoal(13, new WaterAvoidingRandomStrollGoal($this, 0.8));
		$this->goalSelector->addGoal(14, new LookAtEntityGoal($this, Player::class, 6, 0.02));
		$this->goalSelector->addGoal(15, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(0, new DefendTrustedTargetGoal($this, 25));
		$this->addPreyGoals(Rabbit::class, FoxVariant::RED);
		$this->addPreyGoals(Chicken::class, FoxVariant::RED);
		$this->addPreyGoals(AbstractFish::class, FoxVariant::ARCTIC, static fn(Living $fish) : bool => !$fish instanceof Pufferfish);
		$this->addPreyGoals(Turtle::class, FoxVariant::RED, Turtle::isBabyOnLand(...));
		//TODO: Foxes also head for villages at night, and look for cover while it thunders
	}

	/**
	 * Attacks when it defends a trusted player, or at its leisure otherwise.
	 */
	private function createMeleeAttackGoal(bool $whileDefending) : MeleeAttackGoal{
		return new class($this, $whileDefending) extends MeleeAttackGoal{
			public function __construct(private Fox $fox, private bool $whileDefending){
				parent::__construct($fox, 1);
			}

			public function canUse() : bool{
				return $this->fox->isDefending() === $this->whileDefending && parent::canUse();
			}

			public function canContinueToUse() : bool{
				return $this->fox->isDefending() === $this->whileDefending && parent::canContinueToUse();
			}
		};
	}

	/**
	 * Each variant hunts its favorite prey before the rest.
	 *
	 * @phpstan-param class-string<Living> $preyType
	 * @phpstan-param null|Closure(Living) : bool $validator
	 */
	private function addPreyGoals(string $preyType, FoxVariant $favoredBy, ?Closure $validator = null) : void{
		foreach ([true, false] as $favored) {
			$this->targetSelector->addGoal($favored ? 6 : 7, new NearestAttackableGoal(
				entity: $this,
				targetType: $preyType,
				targetValidator: $validator,
				condition: fn() : bool => ($this->variant === $favoredBy) === $favored,
				range: 12
			));
		}
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->initCarriedItem($nbt);

		$this->variant = FoxVariantIdMap::getInstance()->fromId($nbt->getInt(self::TAG_VARIANT, -1)) ?? $this->getSpawnVariant();
		foreach ($nbt->getListTag(self::TAG_TRUSTED_PLAYERS, StringTag::class) ?? [] as $uuid) {
			$this->trustedPlayers[$uuid->getValue()] = true;
		}
	}

	public function saveNBT() : CompoundTag{
		if (count($this->trustedPlayers) !== 0) {
			$this->componentGroups->add(self::COMPONENT_GROUP_TRUSTING);
		}

		$nbt = parent::saveNBT()->merge($this->saveCarriedItem());
		$nbt->setInt(self::TAG_VARIANT, FoxVariantIdMap::getInstance()->toId($this->variant));
		$nbt->setTag(self::TAG_TRUSTED_PLAYERS, new ListTag(array_map(
			static fn(string $uuid) : StringTag => new StringTag($uuid),
			array_keys($this->trustedPlayers)
		)));

		return $nbt;
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);

		$this->setCanPickUpItems();
	}

	protected function updatePickupItemsGoal(bool $enabled) : void{
		if ($enabled) {
			$this->goalSelector->addGoal(11, $this->pickupItemsGoal);
		} else {
			$this->goalSelector->removeGoal($this->pickupItemsGoal);
		}
	}

	/**
	 * It takes any item, preferring food.
	 *
	 * @return array<int, int|Closure(Item): int>
	 */
	public static function getWantedItems() : array{
		//TODO: bundles come between food and the rest once they exist
		return [
			0 => static fn(Item $item) : int => $item instanceof FoodSourceItem ? 1 : 0,
			1 => static fn(Item $item) : int => 1
		];
	}

	public function generateEquipment() : void{
		$this->equipFromLootTable(VanillaLootTables::FOX_EQUIPMENT());
	}

	public function getEquipmentDropProbability(Item $item, Inventory $inventory, int $slot) : float{
		return 1.0;
	}

	public function attackEntity(Entity $entity) : bool{
		return $this->attackEntityWithHeldItem($entity);
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendCarriedItemSpawnPacket($player);
	}

	public function getDrops() : array{
		return $this->getCarriedItemDrops();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, FoxVariantIdMap::getInstance()->toId($this->variant));
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setGenericFlag(EntityMetadataFlags::SLEEPING, $this->isSleeping());
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

	/**
	 * Whether it is a good time and place to sleep: by day, on dry ground, with a roof over it and not inside a wall.
	 */
	private function canNap() : bool{
		//TODO: not while it thunders, PocketMine-MP has no weather
		return Utils::isDaytime($this->getWorld()) &&
			!$this->isInWater() &&
			$this->onGround &&
			!$this->isInsideOfSolid() &&
			!Utils::isSkyVisible($this->getWorld(), $this->getPosition());
	}

	/**
	 * Whether a mob nearby leaves it asleep: foxes, sneaking mobs and the players it trusts.
	 */
	private function staysAsleepFor(Living $other) : bool{
		return $other instanceof self || $other->isSneaking() || ($other instanceof Player && $this->isTrusting($other));
	}

	private function getSpawnVariant() : FoxVariant{
		$isSnowCovered = Utils::isSnowCovered(
			$this->getWorld(),
			$this->location->getFloorX(),
			$this->location->getFloorY(),
			$this->location->getFloorZ()
		);

		return $isSnowCovered ? FoxVariant::ARCTIC : FoxVariant::RED;
	}

	public function getVariant() : FoxVariant{
		return $this->variant;
	}

	/**
	 * @return $this
	 */
	public function setVariant(FoxVariant $variant) : self{
		$this->variant = $variant;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function isTrusting(Player $player) : bool{
		return isset($this->trustedPlayers[$player->getUniqueId()->toString()]);
	}

	public function trust(Player $player) : void{
		$this->trustedPlayers[$player->getUniqueId()->toString()] = true;
	}

	public function isDefending() : bool{
		return $this->defending;
	}

	public function setDefending(bool $defending) : static{
		$this->defending = $defending;

		return $this;
	}

	/**
	 * Whether it takes no fall damage, as when it lands from a pounce.
	 */
	public function setImmuneToFallDamage(bool $immune) : static{
		$this->immuneToFallDamage = $immune;

		return $this;
	}

	protected function calculateFallDamage(float $fallDistance) : float{
		return $this->immuneToFallDamage ? 0 : parent::calculateFallDamage($fallDistance);
	}

	public function attack(EntityDamageEvent $source) : void{
		if ($source instanceof EntityDamageByBlockEvent && $source->getDamager() instanceof SweetBerryBush) {
			$source->cancel();
		}

		parent::attack($source);
	}

	public function isFood(Item $item) : bool{
		return isset(self::FOOD[$item->getTypeId()]);
	}

	/**
	 * The kit trusts the players that fed its parents.
	 */
	public function getBreedOffspring(AgeableMob $partner) : Fox{
		/** @var Fox $partner */
		$offspring = new Fox($this->getLocation());
		$offspring->setVariant(mt_rand(0, 1) === 0 ? $this->variant : $partner->variant);
		foreach ([$this->getLoveCauser(), $partner->getLoveCauser()] as $player) {
			if ($player !== null) {
				$offspring->trust($player);
			}
		}

		return $offspring;
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::FOX_SPAWN_EGG();
	}
}