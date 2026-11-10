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
use IvanCraft623\MobPlugin\inventory\MobInventory;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living as PMLiving;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\timings\Timings;
use function abs;
use function atan2;
use function floor;
use function get_class;
use function max;
use function min;
use function sqrt;
use const M_PI;

/**
 * Patches for PocketMine-MP's Living (5.44.2): what it gets wrong or lacks. Mob AI lives in Mob.
 */
abstract class Living extends PMLiving {
	/** Strength of a single push between two entities. */
	public const ENTITY_PUSH_FORCE = 0.05;
	/** Entities whose box is this close (x and z) to ours push us. */
	public const ENTITY_PUSH_REACH = 0.2;
	/** Entities closer than this on both horizontal axes do not push us. */
	public const ENTITY_PUSH_MIN_DISTANCE = 0.1;
	/** An entity pushes us, and we push it back, so every pair of pushable entities pushes twice per tick. */
	public const ENTITY_PUSHES_PER_PAIR = 2;

	public const WATER_GRAVITY_MULTIPLIER = 0.25;
	private const LIQUID_JUMP_PUSH = 0.04; //blocks per tick, added to the vertical motion

	public const LEVITATION_MOTION_PER_LEVEL = 0.05;
	public const LEVITATION_LERP_FACTOR = 0.2;

	protected float $stepHeight = 0.6;

	protected float $verticalDrag;

	/** Fraction of the motion water takes away each tick, on every axis */
	protected float $waterDrag;

	protected MobInventory $inventory;

	/**
	 * PM only has the body yaw; this is where the head looks, sent in the movement packets.
	 * Remove when PocketMine supports it, see https://github.com/pmmp/PocketMine-MP/issues/6503
	 */
	protected float $headYaw;

	private float $lastHeadYaw;

	/** Server tick the liquid immersion was computed for */
	private int $immersionTick = -1;
	private float $waterImmersion = 0.0;
	private float $lavaImmersion = 0.0;

	protected function getInitialDragMultiplier() : float{ return 0.09; }

	protected function getInitialVerticalDragMultiplier() : float{ return 0.02; }

	protected function getInitialWaterDragMultiplier() : float{ return 0.2; }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->inventory = new MobInventory($this);

		$this->verticalDrag = $this->getInitialVerticalDragMultiplier();
		$this->waterDrag = $this->getInitialWaterDragMultiplier();
		$this->headYaw = $this->lastHeadYaw = $this->location->yaw;

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
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		if (abs(Utils::wrapDegrees($this->headYaw - $this->location->yaw)) > 1.0) {
			//The spawn packet gives the head the body yaw
			$player->getNetworkSession()->sendDataPacket($this->createMovementPacket());
		}
		if (!$this->inventory->getMainHand()->isNull() || !$this->inventory->getOffHand()->isNull()) {
			$this->sendHeldItemsPacket($player);
		}
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
	 * @param Item[] $items
	 * @phpstan-param array<int, Item> $items
	 */
	private static function populateInventoryFromListTag(Inventory $inventory, array $items) : void{
		$listeners = $inventory->getListeners()->toArray();
		$inventory->getListeners()->clear();

		$inventory->setContents($items);

		$inventory->getListeners()->add(...$listeners);
	}

	public function getInventory() : MobInventory{
		return $this->inventory;
	}

	public function getServer() : Server{
		return $this->location->getWorld()->getServer();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		CustomTimings::$entityPush->startTiming();
		$hasUpdate = $this->pushOutOfEntities() || $hasUpdate;
		CustomTimings::$entityPush->stopTiming();

		return $hasUpdate;
	}

	/**
	 * Copy of Entity::onUpdate() (PocketMine-MP 5.44.2) with the motion reductions (tryChangeMovement()) moved after
	 * the move. Re-check it against Entity::onUpdate() when updating PocketMine-MP.
	 */
	public function onUpdate(int $currentTick) : bool{
		if($this->closed){
			return false;
		}

		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0){
			if(!$this->justCreated){
				$this->server->getLogger()->debug("Expected tick difference of at least 1, got $tickDiff for " . get_class($this));
			}

			return true;
		}

		$this->lastUpdate = $currentTick;

		if($this->justCreated){
			$this->onFirstUpdate($currentTick);
		}

		if(!$this->isAlive()){
			if($this->onDeathUpdate($tickDiff)){
				$this->flagForDespawn();
			}

			return true;
		}

		$this->timings->startTiming();

		if($this->hasMovementUpdate()){
			$motionX = abs($this->motion->x) > self::MOTION_THRESHOLD ? $this->motion->x : 0;
			$motionY = abs($this->motion->y) > self::MOTION_THRESHOLD ? $this->motion->y : 0;
			$motionZ = abs($this->motion->z) > self::MOTION_THRESHOLD ? $this->motion->z : 0;

			if(!$this->forceMovementUpdate && $this->onGround && $motionX === 0 && $motionZ === 0 && $motionY < 0 && $this->ySize <= self::MOTION_THRESHOLD){
				//move() would only find the floor again (ySize: a step up still being smoothed)
				$this->checkGroundState(0, $motionY, 0, 0, 0, 0);
				$this->motion = Vector3::zero();
			}else{
				$this->motion = new Vector3($motionX, $motionY, $motionZ);
				if($motionX !== 0 || $motionY !== 0 || $motionZ !== 0){
					$this->move($motionX, $motionY, $motionZ);
					$this->immersionTick = -1;
				}
			}

			$this->tryChangeMovement();

			$this->forceMovementUpdate = false;
		}

		$this->updateMovement();

		Timings::$entityBaseTick->startTiming();
		$hasUpdate = $this->entityBaseTick($tickDiff);
		Timings::$entityBaseTick->stopTiming();

		$this->timings->stopTiming();

		return ($hasUpdate || $this->hasMovementUpdate());
	}

	public function getHeadYaw() : float{
		return $this->headYaw;
	}

	public function setHeadYaw(float $headYaw) : void{
		$this->headYaw = Utils::normalizeDegrees($headYaw);
	}

	/**
	 * Keeps the yaw in [0, 360), as PM's lookAt() does.
	 */
	public function setRotation(float $yaw, float $pitch) : void{
		parent::setRotation(Utils::normalizeDegrees($yaw), $pitch);
	}

	/**
	 * Turns the whole mob toward $target, not just the head like LookControl. Without limits it turns instantly, head
	 * included, as PM does. With them it turns at most that many degrees per tick and leaves the head alone, which
	 * mobs that move relative to their facing, like bow strafing, need.
	 */
	public function lookAt(Vector3 $target, ?float $maxYawChange = null, ?float $maxPitchChange = null) : void{
		if ($maxYawChange === null && $maxPitchChange === null) {
			parent::lookAt($target);

			$this->headYaw = $this->location->yaw;
			return;
		}

		$xDist = $target->x - $this->location->x;
		$zDist = $target->z - $this->location->z;
		$yaw = (atan2($zDist, $xDist) * (180 / M_PI)) - 90;
		$pitch = -(atan2($target->y - ($this->location->y + $this->getEyeHeight()), sqrt($xDist ** 2 + $zDist ** 2)) * (180 / M_PI));

		if ($maxYawChange !== null) {
			$yaw = $this->location->yaw + Utils::clamp(Utils::degreesDifference($this->location->yaw, $yaw), -$maxYawChange, $maxYawChange);
		}
		if ($maxPitchChange !== null) {
			$pitch = $this->location->pitch + Utils::clamp(Utils::degreesDifference($this->location->pitch, $pitch), -$maxPitchChange, $maxPitchChange);
		}

		$this->setRotation($yaw, $pitch);
	}

	/**
	 * A teleported mob's head faces the same way as its body.
	 */
	protected function setPositionAndRotation(Vector3 $pos, float $yaw, float $pitch) : bool{
		if (!parent::setPositionAndRotation($pos, $yaw, $pitch)) {
			return false;
		}

		$this->setHeadYaw($yaw);

		return true;
	}

	/**
	 * PM only sends a movement packet when the body moves or turns.
	 */
	protected function updateMovement(bool $teleport = false) : void{
		parent::updateMovement($teleport);

		if (abs(Utils::wrapDegrees($this->headYaw - $this->lastHeadYaw)) > 1.0) {
			$this->broadcastMovement($teleport);
		}
	}

	/**
	 * Entity::broadcastMovement() sends the body yaw as the head yaw.
	 */
	protected function broadcastMovement(bool $teleport = false) : void{
		$this->lastHeadYaw = $this->headYaw;

		NetworkBroadcastUtils::broadcastPackets($this->hasSpawned, [$this->createMovementPacket()]);
	}

	private function createMovementPacket() : MoveActorAbsolutePacket{
		return MoveActorAbsolutePacket::create(
			$this->id,
			$this->getOffsetPosition($this->location),
			$this->location->pitch,
			$this->location->yaw,
			$this->headYaw,
			$this->onGround ? MoveActorAbsolutePacket::FLAG_GROUND : 0
		);
	}

	/**
	 * PM does not force a movement update on teleports.
	 */
	protected function setPosition(Vector3 $pos) : bool{
		//The floor is only known for where move() left the mob
		$this->setForceMovementUpdate();

		return parent::setPosition($pos);
	}

	/**
	 * Replaces Living::tryChangeMovement(): water drag and gravity, levitation, and climbing.
	 */
	protected function tryChangeMovement() : void{
		$inWater = $this->isInWater();
		$motionX = $this->motion->x;
		$motionY = $this->motion->y;
		$motionZ = $this->motion->z;

		if($inWater){
			$waterFriction = 1 - $this->waterDrag;
			$motionX *= $waterFriction;
			$motionY *= $waterFriction;
			$motionZ *= $waterFriction;
		}

		$levitation = $this->effectManager->get(VanillaEffects::LEVITATION());
		if($levitation !== null){
			$motionY += (self::LEVITATION_MOTION_PER_LEVEL * $levitation->getEffectLevel() - $motionY) * self::LEVITATION_LERP_FACTOR;
		}else{
			//TODO: slow falling (-0.01 while falling) once PM implements the effect.
			$gravity = $this->gravityEnabled ? ($inWater ? $this->gravity * self::WATER_GRAVITY_MULTIPLIER : $this->gravity) : 0;

			$verticalDrag = $inWater || ($this->onClimbable() && $this->isCollidedHorizontally) ?
				1 : 1 - $this->verticalDrag;

			$motionY = $this->applyDragBeforeGravity() ?
				$motionY * $verticalDrag - $gravity :
				($motionY - $gravity) * $verticalDrag;
		}

		//Water replaces the air drag and the friction of the floor
		$xzFriction = $inWater ? 1 : 1 - $this->drag;
		if($this->onGround && !$inWater){
			$xzFriction *= $this->getWorld()->getBlockAt(
				(int) floor($this->location->x),
				(int) floor($this->location->y - 1),
				(int) floor($this->location->z)
			)->getFrictionFactor();
		}

		$this->motion = new Vector3($motionX * $xzFriction, $motionY, $motionZ * $xzFriction);
	}

	/**
	 * Whether the mob is climbing: only climb-capable mobs (Entity::canClimbWalls(),
	 * e.g. spiders) inside a climbable block (ladder, vine) count.
	 */
	protected function onClimbable() : bool{
		return $this->canClimbWalls() && $this->getWorld()->getBlock($this->location)->canClimb();
	}

	/**
	 * PM has no entity pushing.
	 */
	protected function pushOutOfEntities() : bool {
		if(!$this->canBePushed()){
			return false;
		}

		$motionX = $this->motion->x;
		$motionZ = $this->motion->z;

		$x = $this->location->x;
		$z = $this->location->z;
		$strength = self::ENTITY_PUSH_FORCE * self::ENTITY_PUSHES_PER_PAIR;

		$reach = $this->boundingBox->expandedCopy(self::ENTITY_PUSH_REACH, 0, self::ENTITY_PUSH_REACH);
		foreach($this->getWorld()->getCollidingEntities($reach, $this) as $other){
			if(!$this->canBePushedBy($other)){
				continue;
			}

			$dx = $x - $other->location->x;
			$dz = $z - $other->location->z;

			$dist = max(abs($dx), abs($dz));
			if($dist < self::ENTITY_PUSH_MIN_DISTANCE){
				continue;
			}

			$inverseRoot = 1.0 / sqrt($dist);
			$factor = min($inverseRoot, 1.0) * $inverseRoot * $strength;
			$motionX += $dx * $factor;
			$motionZ += $dz * $factor;
		}

		if($motionX !== $this->motion->x || $motionZ !== $this->motion->z){
			$this->setMotion(new Vector3($motionX, $this->motion->y, $motionZ));
			return true;
		}

		return false;
	}

	protected function canBePushed() : bool {
		return true; //TODO: not in or a being a vehicle
	}

	protected function canBePushedBy(Entity $other) : bool {
		return $other instanceof PMLiving; //TODO: other not be a vehicle
	}

	/**
	 * PM's jump() ignores liquids, where a jump is a small push up each tick.
	 */
	public function jump() : void{
		if ($this->getWorld()->getBlock($this->location) instanceof Liquid) {
			$this->motion = $this->motion->withComponents(null, $this->motion->y + self::LIQUID_JUMP_PUSH, null);
		} elseif ($this->onGround) {
			$this->motion = $this->motion->withComponents(null, $this->getJumpVelocity(), null);
		}
	}

	/**
	 * PM ignores jump boost.
	 */
	public function getJumpVelocity() : float{
		if (!$this->onGround) {
			return $this->jumpVelocity;
		}
		return $this->jumpVelocity + ((($jumpBoost = $this->effectManager->get(VanillaEffects::JUMP_BOOST())) !== null ? $jumpBoost->getEffectLevel() : 0) / 10);
	}

	public function isInWater() : bool{
		return $this->getWaterImmersion() > 0;
	}

	public function isInLava() : bool{
		return $this->getLavaImmersion() > 0;
	}

	/**
	 * @return float 0-1
	 */
	public function getWaterImmersion() : float{
		$this->updateImmersion();
		return $this->waterImmersion;
	}

	/**
	 * @return float 0-1
	 */
	public function getLavaImmersion() : float{
		$this->updateImmersion();
		return $this->lavaImmersion;
	}

	/**
	 * PM only knows whether the mob is in a liquid block; this measures how deep, at most once per tick.
	 */
	private function updateImmersion() : void{
		$tick = $this->server->getTick();
		if ($this->immersionTick === $tick) {
			return;
		}
		$this->immersionTick = $tick;

		$waterTop = null;
		$lavaTop = null;

		$world = $this->getWorld();
		$entityHeight = $this->size->getHeight();
		$floorX = (int) floor($this->location->x);
		$floorY = (int) floor($this->location->y);
		$floorZ = (int) floor($this->location->z);
		for ($y = (int) floor($this->location->y + $entityHeight); $y >= $floorY; $y--) {
			$block = $world->getBlockAt($floorX, $y, $floorZ);
			if ($block instanceof Water) {
				$waterTop ??= ($y + 1) - ($block->getFluidHeightPercent() - 0.1111111);
			} elseif ($block instanceof Lava) {
				$lavaTop ??= ($y + 1) - ($block->getFluidHeightPercent() - 0.1111111);
			}
		}

		$this->waterImmersion = $waterTop === null ? 0.0 : min(1.0, ($waterTop - $this->location->y) / $entityHeight);
		$this->lavaImmersion = $lavaTop === null ? 0.0 : min(1.0, ($lavaTop - $this->location->y) / $entityHeight);
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

		$inventoryTag = new ListTag([], NBT::TAG_Compound);
		$nbt->setTag("Inventory", $inventoryTag);

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

		return $nbt;
	}

	/**
	 * Adds onInsideBlock(), for blocks that act on a mob inside them.
	 */
	protected function checkBlockIntersections() : void{
		$hasUpdate = false;
		foreach($this->getBlocksAroundWithEntityInsideActions() as $block){
			if($this->onInsideBlock($block)){
				$hasUpdate = true;
			}
		}

		parent::checkBlockIntersections();

		if($hasUpdate){
			$this->blocksAround = null;
		}
	}

	public function onInsideBlock(Block $block) : bool{
		return false;
	}

	protected function destroyCycles() : void{
		unset($this->inventory);
		parent::destroyCycles();
	}
}
