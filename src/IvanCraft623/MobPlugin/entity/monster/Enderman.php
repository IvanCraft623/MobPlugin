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

use IvanCraft623\MobPlugin\entity\ai\goal\enderman\FreezeWhenLookedAt;
use IvanCraft623\MobPlugin\entity\ai\goal\enderman\LeaveBlockGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\enderman\LookForStaringPlayerGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\enderman\TakeBlockGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomTeleportGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\NeutralMob;
use IvanCraft623\MobPlugin\entity\NeutralMobTrait;
use IvanCraft623\MobPlugin\particle\TeleportTrailParticle;
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;
use IvanCraft623\MobPlugin\sound\EntityStareSound;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\WaterCauldron;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living as PMLiving;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\sound\EndermanTeleportSound;

use function array_merge;
use function count;
use function mt_rand;

class Enderman extends Monster implements NeutralMob{
	use NeutralMobTrait;

	private const TAG_LEGACY_CARRIED = "carried";
	private const TAG_LEGACY_CARRIED_DATA = "carriedData";
	private const TAG_CARRIED_BLOCK = "carriedBlock";

	/**
	 * @var true[]
	 * @phpstan-var array<BlockTypeIds::*, true>
	 */
	public static array $HOLDABLE_BLOCKS = [
		//small flowers
		BlockTypeIds::DANDELION => true,
		BlockTypeIds::POPPY => true,
		BlockTypeIds::BLUE_ORCHID => true,
		BlockTypeIds::ALLIUM => true,
		BlockTypeIds::AZURE_BLUET => true,
		BlockTypeIds::RED_TULIP => true,
		BlockTypeIds::ORANGE_TULIP => true,
		BlockTypeIds::WHITE_TULIP => true,
		BlockTypeIds::PINK_TULIP => true,
		BlockTypeIds::OXEYE_DAISY => true,
		BlockTypeIds::CORNFLOWER => true,
		BlockTypeIds::LILY_OF_THE_VALLEY => true,
		BlockTypeIds::WITHER_ROSE => true,
		//BlockTypeIds::TORCHFLOWER => true,

		//dirts...
		BlockTypeIds::DIRT => true,
		BlockTypeIds::GRASS => true,
		BlockTypeIds::PODZOL => true,
		BlockTypeIds::MYCELIUM => true,
		//TODO: moss block
		BlockTypeIds::MUD => true,
		BlockTypeIds::MUDDY_MANGROVE_ROOTS => true,

		//other blocks
		BlockTypeIds::SAND => true,
		BlockTypeIds::RED_SAND => true,
		BlockTypeIds::GRAVEL => true,
		BlockTypeIds::BROWN_MUSHROOM => true,
		BlockTypeIds::RED_MUSHROOM => true,
		BlockTypeIds::TNT => true,
		BlockTypeIds::CACTUS => true,
		BlockTypeIds::CLAY => true,
		BlockTypeIds::PUMPKIN => true,
		BlockTypeIds::CARVED_PUMPKIN => true,
		BlockTypeIds::MELON => true
		//TODO: crimson fungus
		//TODO: crimson nylium
		//BlockTypeIds::CRIMSON_ROOTS => true,
		//TODO: warped fungus
		//TODO: warped nylium
		//BlockTypeIds::WARPED_ROOTS => true
	];

	public const MIN_ANGER_TIME = 20;
	public const MAX_ANGER_TIME = 40;

	public static function getNetworkTypeId() : string{ return EntityIds::ENDERMAN; }

	public static function isHoldableBlock(Block $block) : bool{
		return self::$HOLDABLE_BLOCKS[$block->getTypeId()] ?? false;
	}

	protected float $stepHeight = 1.0625; //1 block + 1 pixel

	private ?Block $carryBlock = null;

	private int $remainingAngerTime = 0;

	protected RandomTeleportGoal $teleportGoal;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.9, 0.6, 2.55);
	}

	public function getName() : string{
		return "Enderman";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new FreezeWhenLookedAt($this));
		$this->goalSelector->addGoal(2, new MeleeAttackGoal($this, 1, false));
		$this->goalSelector->addGoal(3, ($this->teleportGoal = new RandomTeleportGoal(entity: $this,
			randomTeleportRange: new Vector3(32, 32, 32),
			maxTeleportInterval: 30 * 20)
		));
		$this->goalSelector->addGoal(7, new WaterAvoidingRandomStrollGoal($this, 1, 0));
		$this->goalSelector->addGoal(8, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(8, new RandomLookAroundGoal($this));
		$this->goalSelector->addGoal(8, new LeaveBlockGoal($this));
		$this->goalSelector->addGoal(8, new TakeBlockGoal($this));
		//TODO: enderman only goals

		$this->targetSelector->addGoal(1, new LookForStaringPlayerGoal($this));
		$this->targetSelector->addGoal(2, new HurtByTargetGoal($this));
		$this->targetSelector->addGoal(3, new NearestAttackableGoal($this, Endermite::class));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$blockStateData = null;

		$blockDataUpgrader = GlobalBlockStateHandlers::getUpgrader();
		if(($itemIdTag = $nbt->getTag(self::TAG_LEGACY_CARRIED)) instanceof ShortTag && ($itemMetaTag = $nbt->getTag(self::TAG_LEGACY_CARRIED_DATA)) instanceof IntTag){
			try{
				$blockStateData = $blockDataUpgrader->upgradeIntIdMeta($itemIdTag->getValue(), $itemMetaTag->getValue());
			}catch(BlockStateDeserializeException $e){
				throw new SavedDataLoadingException("Error loading legacy enderman carried block data: " . $e->getMessage(), 0, $e);
			}
		}elseif(($carriedBlockTag = $nbt->getCompoundTag(self::TAG_CARRIED_BLOCK)) !== null){
			try{
				$blockStateData = $blockDataUpgrader->upgradeBlockStateNbt($carriedBlockTag);
			}catch(BlockStateDeserializeException $e){
				throw new SavedDataLoadingException("Error loading " . self::TAG_CARRIED_BLOCK . " tag for enderman: " . $e->getMessage(), 0, $e);
			}
		}

		if($blockStateData !== null){
			try{
				$blockStateId = GlobalBlockStateHandlers::getDeserializer()->deserialize($blockStateData);
			}catch(BlockStateDeserializeException $e){
				throw new SavedDataLoadingException("Error deserializing carried block for enderman: " . $e->getMessage(), 0, $e);
			}

			$carriedBlock = RuntimeBlockStateRegistry::getInstance()->fromStateId($blockStateId);
			if ($carriedBlock->getTypeId() !== BlockTypeIds::AIR) {
				$this->setCarriedBlock(RuntimeBlockStateRegistry::getInstance()->fromStateId($blockStateId));
			}
		}

		parent::initEntity($nbt);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		if($this->carryBlock !== null){
			$nbt->setTag(self::TAG_CARRIED_BLOCK, GlobalBlockStateHandlers::getSerializer()->serialize($this->carryBlock->getStateId())->toNbt());
		}

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::ENDERMAN_HELD_ITEM_ID,
			TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId(
				($this->carryBlock ?? VanillaBlocks::AIR())->getStateId()
			)
		);

		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry());
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(40);
		$this->setAttackDamage(7);
		$this->setFollowRange(64);

		$this->setPathfindingMalus(BlockPathTypes::WATER(), -1);
	}

	public function getDefaultMovementSpeed() : float{
		return $this->isAngry() ? 0.45 : 0.3;
	}

	public function getXpDropAmount() : int{
		if ($this->hasBeenDamagedByPlayer()) {
			return 5;
		}

		return 0;
	}

	public function getDrops() : array{
		$drops = parent::getDrops();
		$drops[] = VanillaItems::ENDER_PEARL()->setCount(mt_rand(0, 1)); //TODO: looting...

		if ($this->carryBlock !== null) {
			$drops = array_merge($drops, $this->carryBlock->getSilkTouchDrops(VanillaItems::DIAMOND_AXE()));
		}

		return $drops;
	}

	public function startAngerTimer() : void{
		$this->setRemainingAngerTime(mt_rand(self::MIN_ANGER_TIME, self::MAX_ANGER_TIME));
	}

	public function getRemainingAngerTime() : int{
		return $this->remainingAngerTime;
	}

	public function setRemainingAngerTime(int $ticks) : void{
		$this->remainingAngerTime = $ticks;
	}

	public function isLookingAtMe(PMLiving $entity) : bool{
		if ($entity->getArmorInventory()->getHelmet()->getTypeId() === ItemTypeIds::fromBlockTypeId(BlockTypeIds::CARVED_PUMPKIN)) {
			return false;
		}

		$diff = $this->getEyePos()->subtractVector($entity->getEyePos());
		$distance = $diff->length();
		$diff = $diff->normalize();
		$dotProduct = $entity->getDirectionVector()->dot($diff);
		return $dotProduct > (1 - (0.025 / $distance)) ? $this->canSee($entity) : false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$this->updateAnger(true);

		return $hasUpdate;
	}

	public function isSensitiveToWater() : bool{
		return true;
	}

	public function getCarriedBlock() : ?Block{
		return $this->carryBlock;
	}

	public function setCarriedBlock(?Block $block) : void{
		$this->carryBlock = $block;
		$this->networkPropertiesDirty = true;
	}

	public function isPersistenceRequired() : bool{
		return parent::isPersistenceRequired() || $this->carryBlock !== null;
	}

	public function onBeingStaredAt() : void{
		$this->broadcastSound(new EntityStareSound($this));
	}

	public function placeBlock(Vector3 $pos) : bool{
		$block = $this->carryBlock;

		//Enderman place block conditions
		if ($block === null) {
			return false;
		}

		$world = $this->getWorld();
		$blockReplace = $world->getBlock($pos);
		if ($blockReplace->getTypeId() !== BlockTypeIds::AIR) {
			return false;
		}

		$blockBelow = $world->getBlock($pos->down());
		if (($belowTypeId = $blockBelow->getTypeId()) === BlockTypeIds::AIR || $belowTypeId === BlockTypeIds::BEDROCK) {
			return false;
		}

		if (!$blockBelow->isFullCube()) {
			return false;
		}

		//Place block logic
		$block->position($world, (int) $pos->x, (int) $pos->y, (int) $pos->z);
		$face = Facing::UP;
		$clickVector = new Vector3(0.5, 1, 0.5);
		if(!$block->canBePlacedAt($blockReplace, $clickVector, $face, false)){
			return false;
		}

		$tx = new BlockTransaction($world);
		if(!$block->place($tx, $block->asItem(), $blockReplace, $blockBelow, $face, $clickVector, null)){
			return false;
		}

		foreach($tx->getBlocks() as [$x, $y, $z, $block]){
			$block->position($world, $x, $y, $z);
			foreach($block->getCollisionBoxes() as $collisionBox){
				if(count($world->getCollidingEntities($collisionBox)) > 0){
					return false;  //Entity in block
				}
			}
		}

		//TODO: maybe make an event for this

		return $tx->apply();
	}

	public function canStandAt(Vector3 $pos) : bool{
		if (!parent::canStandAt($pos)) {
			return false;
		}

		$diff = $this->location->subtractVector($pos);
		foreach ($this->getWorld()->getCollisionBlocks($this->boundingBox->addCoord($diff->x, $diff->y, $diff->z)) as $block) {
			if ($block instanceof Liquid) {
				return false;
			}
		}
		return true;
	}

	public function onRandomTeleport(Vector3 $from, Vector3 $to) : void{
		$world = $this->getWorld();

		$world->addParticle($from, new TeleportTrailParticle($to, $this->size->getWidth(), $this->size->getHeight()));

		$world->addSound($from, new EndermanTeleportSound());
		$world->addSound($to, new EndermanTeleportSound());
	}

	public function onInsideBlock(Block $block) : bool{
		$hasUpdate = parent::onInsideBlock($block);

		if ($block->getTypeId() === BlockTypeIds::WATER || $block instanceof WaterCauldron) {
			$this->attack(new EntityDamageByBlockEvent($block, $this, EntityDamageEvent::CAUSE_DROWNING, 1));

			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function attack(EntityDamageEvent $source) : void{

		//Projectiles cannot damage endermans
		$cause = $source->getCause();
		if ($cause === EntityDamageEvent::CAUSE_PROJECTILE) {
			$source->cancel();
		}

		parent::attack($source);

		if ($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
			if (mt_rand(0, 1) === 0) {
				$this->teleportGoal->setTeleportTick(0);
			}
		} elseif (!$source->isCancelled() || $cause === EntityDamageEvent::CAUSE_PROJECTILE) {
			$this->teleportGoal->setTeleportTick(0);
		}
	}

	//TODO: try to teleport when damaged

	//TODO: spawn rules code
}
