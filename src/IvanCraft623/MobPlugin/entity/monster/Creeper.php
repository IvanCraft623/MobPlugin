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

use IvanCraft623\MobPlugin\entity\ai\goal\creeper\SwellGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Explosive;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\utils\Binary;
use pocketmine\world\Explosion;
use pocketmine\world\sound\IgniteSound;
use function mt_rand;

class Creeper extends Monster implements Explosive{

	private const TAG_FUSE = "Fuse"; //TAG_Byte
	private const TAG_IGNITED = "IsFuseLit"; //TAG_Byte

	private const COMPONENT_GROUP_POWERED = "minecraft:charged_creeper";
	private const COMPONENT_GROUP_FORCED_IGNITE = "minecraft:forced_exploding";
	private const COMPONENT_GROUP_POWERED_FORCED_IGNITE = "minecraft:forced_charged_exploding";

	public const DEFAULT_FUSE_TICKS = 30;

	public const DEFAULT_EXPLOSION_RADIUS = 3;

	public static function getNetworkTypeId() : string{ return EntityIds::CREEPER; }

	protected bool $ignited = false;

	protected bool $powered = false;

	protected int $fuse = self::DEFAULT_FUSE_TICKS;

	protected bool $isSwelling = false;

	protected int $explosionRadius = self::DEFAULT_EXPLOSION_RADIUS;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6, 1.62);
	}

	public function getName() : string{
		return "Creeper";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new FloatGoal($this));
		$this->goalSelector->addGoal(2, new SwellGoal($this));
		//TODO: avoid ocelots and cats goal
		$this->goalSelector->addGoal(4, new MeleeAttackGoal($this, 1.25, false));
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(6, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, new NearestAttackableGoal($this, Player::class));
		$this->targetSelector->addGoal(2, new HurtByTargetGoal($this));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->fuse = $nbt->getByte(self::TAG_FUSE, self::DEFAULT_FUSE_TICKS);
		$this->isSwelling = $nbt->getByte(self::TAG_IGNITED, 0) !== 0;

		$this->powered = $this->componentGroups->has(self::COMPONENT_GROUP_POWERED);
		$this->ignited = $this->componentGroups->has(self::COMPONENT_GROUP_FORCED_IGNITE) || $this->componentGroups->has(self::COMPONENT_GROUP_POWERED_FORCED_IGNITE);
	}

	public function saveNBT() : CompoundTag{

		//TODO: this is a hack and we are only saving the minimum for our implementation.
		if ($this->powered) {
			$this->componentGroups->add(self::COMPONENT_GROUP_POWERED);
		}
		if ($this->ignited) {
			$this->componentGroups->add(!$this->powered ? self::COMPONENT_GROUP_FORCED_IGNITE : self::COMPONENT_GROUP_POWERED_FORCED_IGNITE);
		}

		$nbt = parent::saveNBT();

		$nbt->setByte(self::TAG_FUSE, Binary::signByte($this->fuse));
		$nbt->setByte(self::TAG_IGNITED, $this->isSwelling ? 1 : 0);

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::FUSE_LENGTH, $this->fuse);

		$properties->setGenericFlag(EntityMetadataFlags::POWERED, $this->powered);
		$properties->setGenericFlag(EntityMetadataFlags::IGNITED, $this->isSwelling);
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setAttackDamage(3);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.2;
	}

	public function getMaxFallDistance() : int{
		$maxFallDistance = 3;
		if ($this->getTargetEntityId() !== null) {
			$maxFallDistance += (int) ($this->getHealth() - 1);
		}

		return $maxFallDistance;
	}

	protected function onHitGround() : ?float{
		$newVerticalVelocity = parent::onHitGround();

		$this->fuse -= (int) ($this->fallDistance * 1.5);
		if ($this->fuse < 5) {
			$this->fuse = 5;
		}

		return $newVerticalVelocity;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$hasFuseUpdate = false;
		if (!$this->isFlaggedForDespawn()) {
			$oldFuse = $this->fuse;

			if ($this->isIgnited()) {
				$this->setSwelling();
			}

			if ($this->isSwelling) {
				if ($this->fuse <= 0) {
					$this->fuse = 0;
					$this->flagForDespawn();
					$this->explode();
				}
				$this->fuse--;
			} else {
				$this->fuse = self::DEFAULT_FUSE_TICKS;
			}

			$hasFuseUpdate = $oldFuse !== $this->fuse;
			if ($hasFuseUpdate) {
				$this->networkPropertiesDirty = true;
			}

		}

		return $hasUpdate || $hasFuseUpdate;
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	/**
	 * @return $this
	 */
	public function setPowered(bool $powered = true) : self{
		$this->powered = $powered;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function isSwelling() : bool{
		return $this->isSwelling;
	}

	public function setSwelling(bool $swelling = true) : self{
		if (!$this->isSwelling && $swelling) {
			$this->broadcastSound(new IgniteSound());
		}

		$this->isSwelling = $swelling;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function isIgnited() : bool{
		return $this->ignited;
	}

	/**
	 * @return $this
	 */
	public function setIgnited(bool $ignited = true) : self{
		$this->ignited = $ignited;

		return $this;
	}

	public function getFuse() : int{
		return $this->fuse;
	}

	public function setFuse(int $fuse) : void{
		$this->fuse = $fuse;
		$this->networkPropertiesDirty = true;
	}

	public function onLightningBoltHit() : bool{
		if (!$this->isPowered()) {
			$this->setPowered();

			return true;
		}

		return parent::onLightningBoltHit();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if ($item->getTypeId() === ItemTypeIds::FLINT_AND_STEEL && !$this->isSwelling()) {
			$this->setIgnited();
			Utils::damageItemInHand($player);

			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	public function explode() : void{
		$ev = new EntityPreExplodeEvent($this, $this->explosionRadius * ($this->isPowered() ? 2 : 1));
		$ev->call();
		if(!$ev->isCancelled()){
			//TODO: deal with underwater option (underwater treats water as if it has a blast resistance of 0)
			$explosion = new Explosion($this->location, $ev->getRadius(), $this);
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();

			//TODO: spawn area effect cloud if the creeper has some effect
		}
	}

	public function getDrops() : array{
		//TODO: Drop a random disc when it's killed by a skeleton
		return [VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2))];
	}

	public function getXpDropAmount() : int{
		return $this->hasBeenDamagedByPlayer() ? 5 : 0;
	}
}
