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

use IvanCraft623\MobPlugin\entity\animation\TameAnimation;
use IvanCraft623\MobPlugin\event\MobTameEvent;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;

/**
 * Owner and tamed state on top of PocketMine's owning entity. The owner is saved as a UUID and relinked lazily.
 *
 * @phpstan-require-extends PathfinderMob
 * @phpstan-require-implements Tameable
 */
trait TameableTrait{

	private const TAG_OWNER_UUID = "OwnerUUID"; //TAG_String
	private const TAG_TAMED = "IsTamed"; //TAG_Byte

	/**
	 * Called once the mob is tamed.
	 */
	abstract protected function onTamed() : void;

	private ?string $ownerRawUuid = null;

	private bool $tamed = false;

	protected function initEntity(CompoundTag $nbt) : void{
		$this->tamed = $nbt->getByte(self::TAG_TAMED, 0) !== 0;
		$ownerUuid = $nbt->getString(self::TAG_OWNER_UUID, "");
		$this->ownerRawUuid = $ownerUuid !== "" ? Uuid::fromString($ownerUuid)->getBytes() : null;
	}

	public function saveNBT() : CompoundTag{
		$nbt = CompoundTag::create()->setByte(self::TAG_TAMED, $this->tamed ? 1 : 0);
		if ($this->ownerRawUuid !== null) {
			$nbt->setString(self::TAG_OWNER_UUID, Uuid::fromBytes($this->ownerRawUuid)->toString());
		}

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->isTamed());
		$properties->setLong(EntityMetadataProperties::OWNER_EID, $this->getOwningEntityId() ?? -1);
	}

	public function getOwningEntityId() : ?int{
		if ($this->ownerRawUuid !== null) {
			$ownerId = $this->server->getPlayerByRawUUID($this->ownerRawUuid)?->getId();
			if ($ownerId !== $this->ownerId) {
				$this->ownerId = $ownerId;
				$this->networkPropertiesDirty = true;
			}
		}

		return $this->ownerId;
	}

	public function getOwningEntity() : ?Entity{
		$ownerId = $this->getOwningEntityId();

		return $ownerId !== null ? $this->server->getWorldManager()->findEntity($ownerId) : null;
	}

	public function isTamed() : bool{
		return $this->tamed;
	}

	/**
	 * TODO: an owner whose player data is gone is not detected, only a missing owner.
	 */
	public function isOrphaned() : bool{
		return $this->tamed && $this->ownerRawUuid === null && $this->ownerId === null;
	}

	public function setOwningEntity(?Entity $owner) : void{
		if ($owner !== null && $owner->isClosed()) {
			throw new \InvalidArgumentException("Supplied owning entity is garbage and cannot be used");
		}

		//Only a player's UUID outlives the session
		$this->ownerRawUuid = $owner instanceof Player ? $owner->getUniqueId()->getBytes() : null;
		$this->ownerId = $owner?->getId();
		$this->networkPropertiesDirty = true;
	}

	public function tame(Player $tamer) : bool{
		$event = new MobTameEvent($this, $tamer);
		$event->call();
		if ($event->isCancelled()) {
			$this->broadcastAnimation(new TameAnimation($this, false));

			return false;
		}

		$this->becomeTamed($tamer);
		$this->broadcastAnimation(new TameAnimation($this, true));

		return true;
	}

	/**
	 * Tames the mob without the event, for the offspring of a tamed mob.
	 */
	protected function becomeTamed(?Entity $owner) : void{
		$this->setOwningEntity($owner);
		$this->tamed = true;
		$this->setTargetEntity(null);
		$this->navigation->stop();
		$this->networkPropertiesDirty = true;
		//The viewers get the tamed state before what onTamed() sends, like the health that changes with it
		$this->sendData(null);
		$this->onTamed();
	}

	/**
	 * Tells everyone that the mob died, if its owner is around.
	 *
	 * TODO: the show death messages game rule
	 */
	protected function onDeath() : void{
		if ($this->tamed && $this->getOwningEntity() !== null) {
			$this->getServer()->broadcastMessage(PlayerDeathEvent::deriveMessage($this->getDisplayName(), $this->getLastDamageCause()));
		}
	}
}
