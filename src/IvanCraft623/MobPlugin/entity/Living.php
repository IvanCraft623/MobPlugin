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

use IvanCraft623\MobPlugin\entity\ai\Brain;
use IvanCraft623\MobPlugin\inventory\MobInventory;
use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Living as PMLiving;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Random;
use pocketmine\world\Position;
use function count;
use function floor;
use function min;

abstract class Living extends PMLiving {
	//TODO!

	private const TAG_COMPONENT_GROUPS = "definitions"; //TAG_List

	protected Random $random;

	protected ComponentGroups $componentGroups;

	protected float $stepHeight = 0.6;

	protected float $jumpVelocity = 0.475;

	protected int $noActionTime = 0; //TODO: logic

	protected MobInventory $inventory;

	protected Brain $brain;

	protected ?EntityDamageByEntityEvent $lastDamageByEntity = null;

	protected int $lastDamageByEntityTick = -1; //server tick

	protected bool $hasBeenDamagedByPlayer = false;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->random = MobPlugin::getInstance()->getRandom();
		$this->inventory = new MobInventory($this);

		if (($componentGroupsTag = $nbt->getTag(self::TAG_COMPONENT_GROUPS)) instanceof ListTag) {
			$this->componentGroups = ComponentGroups::fromListTag($componentGroupsTag);
		} else {
			$this->componentGroups = new ComponentGroups();
			if ($this->canSaveWithChunk()) {
				$this->componentGroups->add(EntityFactory::getInstance()->getSaveId($this::class));
			}
		}
		$syncHeldItem = function() : void{
			foreach($this->getViewers() as $viewer){
				$this->sendHeldItemsPacket($viewer);
			}
		};
		$this->inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(fn() => $syncHeldItem()));
		$inventoryTag = $nbt->getListTag("Inventory");
		if($inventoryTag !== null){
			$inventoryItems = [];
			$armorInventoryItems = [];

			/** @var CompoundTag $item */
			foreach($inventoryTag as $i => $item){
				$slot = $item->getByte("Slot");
				if($slot >= 0 && $slot < $this->inventory->getSize()){ //Inventory
					$inventoryItems[$slot] = Item::nbtDeserialize($item);
				}elseif($slot >= 100 && $slot < 104){ //Armor
					$armorInventoryItems[$slot - 100] = Item::nbtDeserialize($item);
				}
			}

			self::populateInventoryFromListTag($this->inventory, $inventoryItems);
			self::populateInventoryFromListTag($this->armorInventory, $armorInventoryItems);
		}

		if ($nbt->count() === 0) { //Entity just created!
			$this->onCreate();
		}

		$this->brain = $this->makeBrain();
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendHeldItemsPacket($player);
	}

	protected function sendHeldItemsPacket(Player $player) : void{
		$networksession = $player->getNetworkSession();
		$networksession->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($networksession->getTypeConverter()->coreItemStackToNet($this->inventory->getMainHand())),
			$this->inventory->getHeldItemIndex(),
			$this->inventory->getHeldItemIndex(),
			ContainerIds::INVENTORY
		));
		$networksession->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($networksession->getTypeConverter()->coreItemStackToNet($this->inventory->getOffHand())),
			0,
			0,
			ContainerIds::OFFHAND
		));
	}

	/**
	 * Called when this entity has just been created and is completely clean.
	 */
	public function onCreate() : void{
		$this->generateEquipment();
	}

	public function generateEquipment() : void{}

	protected function makeBrain() : Brain{
		return new Brain([], [], []);
	}

	/**
	 * @param Item[] $items
	 * @phpstan-param array<int, Item> $items
	 */
	private static function populateInventoryFromListTag(Inventory $inventory, array $items) : void{
		$listeners = $inventory->getListeners()->toArray();
		$inventory->getListeners()->clear();

		$inventory->setContents($items);

		$inventory->getListeners()->add(...$listeners);
	}

	public function getRandom() : Random {
		return $this->random;
	}

	public function getDefaultMovementSpeed() : float {
		return 1.0;
	}

	public function getMaxUpStep() : float {
		return $this->stepHeight;
	}

	public function getInventory() : MobInventory{
		return $this->inventory;
	}

	public function getBrain() : Brain{
		return $this->brain;
	}

	public function getServer() : Server{
		return $this->location->getWorld()->getServer();
	}

	public function canAttack(PMLiving $target) : bool {
		return true;
	}

	public function isSensitiveToWater() : bool{
		return false;
	}

	public function canSee(Entity $entity) : bool{
		$start = $this->getEyePos();
		$end = $entity->getEyePos();
		$directionVector = $end->subtractVector($start)->normalize();
		if ($directionVector->lengthSquared() > 0) {
			foreach(VoxelRayTrace::betweenPoints($start, $end) as $vector3){
				$block = $this->getWorld()->getBlockAt((int) $vector3->x, (int) $vector3->y, (int) $vector3->z);

				$blockHitResult = $block->calculateIntercept($start, $end);
				if(!$block->isTransparent() && $blockHitResult !== null){
					return false;
				}
			}
		}
		return true;
	}

	public function jump() : void{
		if ($this->getWorld()->getBlock($this->location) instanceof Liquid) {
			//Hardcode liquid friction because PM doesn't implement it yet :(
			$this->motion = $this->motion->withComponents(null, $this->getJumpVelocity() * 0.55, null);
		} elseif ($this->onGround) {
			$this->motion = $this->motion->withComponents(null, $this->getJumpVelocity(), null);
		}
	}

	public function getJumpVelocity() : float{
		if (!$this->onGround) {
			return $this->jumpVelocity;
		}
		return $this->jumpVelocity + ((($jumpBoost = $this->effectManager->get(VanillaEffects::JUMP_BOOST())) !== null ? $jumpBoost->getEffectLevel() : 0) / 10);
	}

	public function isInWater() : bool{
		return $this->getImmersionPercentage(VanillaBlocks::WATER()) > 0;
	}

	public function isInLava() : bool{
		return $this->getImmersionPercentage(VanillaBlocks::LAVA()) > 0;
	}

	/**
	 * Returns the immersion percentage in the specified liquid.
	 *
	 * @return float 0-1
	 */
	public function getImmersionPercentage(Liquid $liquid) : float{
		$entityHeight = $this->getSize()->getHeight();
		$floorX = (int) floor($this->location->x);
		$floorY = (int) floor($this->location->y);
		$floorZ = (int) floor($this->location->z);
		for ($y = (int) floor($this->location->y + $entityHeight); $y >= $floorY; $y--) {
			$block = $this->getWorld()->getBlockAt($floorX, $y, $floorZ);
			if ($block instanceof $liquid) {
				$liquidHeigh = ($y + 1) - ($block->getFluidHeightPercent() - 0.1111111);
				return min(1, ($liquidHeigh - $this->location->y) / $entityHeight);
			}
		}
		return 0;
	}

	public function getFluidJumpThreshold() : float{
		return $this->getEyeHeight() < 0.4 ? 0 : 0.4;
	}

	public function canStandOnFluid(Liquid $liquid) : bool{
		return false;
	}

	public function getMaxFallDistance() : int{
		return 3;
	}

	public function getNoActionTime() : int{
		return $this->noActionTime;
	}

	public function setNoActionTime(int $time) : void{
		$this->noActionTime = $time;
	}

	public function getKnockbackResistance() : float{
		return $this->knockbackResistanceAttr->getValue();
	}

	public function setKnockbackResistance(float $value) : void{
		$this->knockbackResistanceAttr->setValue($value);
	}

	public function getStepHeight() : float{
		return $this->stepHeight;
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT();

		$nbt->setTag(self::TAG_COMPONENT_GROUPS, $this->componentGroups->toListTag());

		$inventoryTag = new ListTag([], NBT::TAG_Compound);
		$nbt->setTag("Inventory", $inventoryTag);
		if($this->inventory !== null){
			//Normal inventory
			for($slot = 0; $slot < $this->inventory->getSize(); ++$slot){
				$item = $this->inventory->getItem($slot);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($slot));
				}
			}

			//Armor
			for($slot = 100; $slot < 104; ++$slot){
				$item = $this->armorInventory->getItem($slot - 100);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($slot));
				}
			}
		}

		return $nbt;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if (!$source->isCancelled()) {
			$this->noActionTime = 0;
		}
	}

	public function setLastDamageCause(EntityDamageEvent $type) : void{
		parent::setLastDamageCause($type);

		if ($type instanceof EntityDamageByEntityEvent) {
			$this->setLastDamageByEntity($type);

			if ($type->getDamager() instanceof Player) {
				$this->hasBeenDamagedByPlayer = true;
			}
		}
	}

	public function getLastDamageByEntity() : ?EntityDamageByEntityEvent{
		return $this->lastDamageByEntity;
	}

	public function setLastDamageByEntity(?EntityDamageByEntityEvent $type) : void{
		$this->lastDamageByEntity = $type;
		if ($type === null) {
			$this->lastDamageByEntityTick = -1;
		} else {
			$this->lastDamageByEntityTick = $this->getWorld()->getServer()->getTick();
		}
	}

	public function getExpirableLastDamageByEntity() : ?EntityDamageByEntityEvent{
		if ($this->getWorld()->getServer()->getTick() - $this->lastDamageByEntityTick > 100) {
			return null;
		}

		return $this->lastDamageByEntity;
	}

	public function getLastDamageByEntityTick() : int{
		return $this->lastDamageByEntityTick;
	}

	public function hasBeenDamagedByPlayer() : bool{
		return $this->hasBeenDamagedByPlayer;
	}

	protected function shouldDropCookedItems() : bool{
		$deathCause = $this->getLastDamageCause()?->getCause() ?? null;
		return $this->isOnFire() || (!$this->isAlive() && (
			$deathCause === EntityDamageEvent::CAUSE_FIRE ||
			$deathCause === EntityDamageEvent::CAUSE_FIRE_TICK ||
			$deathCause === EntityDamageEvent::CAUSE_LAVA
		));
	}

	protected function checkBlockIntersections() : void{
		$vectors = [];

		foreach($this->getBlocksAroundWithEntityInsideActions() as $block){
			if(!$block->onEntityInside($this) || $this->onInsideBlock($block)){
				$this->blocksAround = null;
			}
			if(($v = $block->addVelocityToEntity($this)) !== null){
				$vectors[] = $v;
			}
		}

		if(count($vectors) > 0){
			$vector = Vector3::sum(...$vectors);
			if($vector->lengthSquared() > 0){
				$d = 0.014;
				$this->motion = $this->motion->addVector($vector->normalize()->multiply($d));
			}
		}
	}

	public function onInsideBlock(Block $block) : bool{
		return false;
	}

	public function getLightLevelDependentMagicValue() : float{
		return Utils::getLightLevelDependentMagicValue(Position::fromObject($this->getEyePos(), $this->location->world));
	}

	protected function destroyCycles() : void{
		$this->lastDamageByEntity = null;

		unset(
			$this->inventory,
		);
		parent::destroyCycles();
	}
}
