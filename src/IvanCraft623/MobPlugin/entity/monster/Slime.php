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

use Closure;
use IvanCraft623\MobPlugin\data\bedrock\SlimeTypeIdMap;
use IvanCraft623\MobPlugin\entity\ai\goal\slime\SlimeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\slime\SlimeFloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\slime\SlimeKeepOnJumpingGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\slime\SlimeRandomDirectionGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\MobCategory;
use IvanCraft623\MobPlugin\entity\monster\slime\SlimeMoveControl;

use IvanCraft623\MobPlugin\entity\monster\slime\SlimeType;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function array_rand;

class Slime extends Mob implements Enemy {

	private const BOUNCE_METADATA_PROPERTY = 24; //byte
	private const BOUNCE_SQUISH = 1;
	private const BOUNCE_STRETCH = 2;

	private const TAG_TYPE = "Variant"; //TAG_Int

	public static function getNetworkTypeId() : string{ return EntityIds::SLIME; }

	protected float $jumpVelocity = 0.52;

	protected SlimeType $type;

	protected bool $jumping = false;

	/** @phpstan-var Closure(Living) : bool */
	protected Closure $attackableValidator;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		if (isset($this->type)) {
			$scale = $this->type->getScale();
		} else {
			$scale = 4;
		}

		return new EntitySizeInfo($scale * 0.52, $scale * 0.52);
	}

	public function getName() : string{
		return "Slime";
	}

	public function getMobCategory() : MobCategory{
		return MobCategory::MONSTER();
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new SlimeFloatGoal($this));
		$this->goalSelector->addGoal(2, new SlimeAttackGoal($this));
		$this->goalSelector->addGoal(3, new SlimeRandomDirectionGoal($this));
		$this->goalSelector->addGoal(5, new SlimeKeepOnJumpingGoal($this));

		$this->attackableValidator = function(Living $entity) : bool{
			return $entity instanceof Player; //TODO: Attack iron golems and snow golems.
		};
		$this->targetSelector->addGoal(1, new NearestAttackableGoal($this, Living::class, targetValidator: $this->attackableValidator));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$slimeType = SlimeTypeIdMap::getInstance()->fromId($nbt->getInt(self::TAG_TYPE, -1));
		if ($slimeType === null) {
			$slimeTypes = SlimeType::getAll();
			$slimeType = $slimeTypes[array_rand($slimeTypes)];
		}
		$this->setType($slimeType);

		parent::initEntity($nbt);

		$this->moveControl = new SlimeMoveControl($this);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_TYPE, SlimeTypeIdMap::getInstance()->toId($this->type));

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, SlimeTypeIdMap::getInstance()->toId($this->type));
		if ($this->onGround) { //Only update bounce state when is on ground
			$properties->setByte(self::BOUNCE_METADATA_PROPERTY, $this->jumping ? self::BOUNCE_STRETCH : self::BOUNCE_SQUISH);
		}
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->recalculateTypeProperties();
	}

	protected function recalculateTypeProperties() : void{
		$this->setSize($this->getInitialSizeInfo());

		$this->setMaxHealth($this->type->getHealth());
		$this->setHealth($this->type->getHealth());
		$this->setMovementSpeed($this->getDefaultMovementSpeed());
		$this->setAttackDamage($this->type->getAttackDamage());
	}

	public function getMoveControl() : SlimeMoveControl{
		/** @var SlimeMoveControl*/
		$moveControl = $this->moveControl;

		return $moveControl;
	}

	public function getDefaultMovementSpeed() : float{
		return $this->type->getMovementSpeed();
	}

	public function shouldDespawnInPeaceful() : bool{
		return $this->type->getAttackDamage() > 0;
	}

	public function getType() : SlimeType{
		return $this->type;
	}

	/**
	 * @return $this
	 */
	public function setType(SlimeType $type) : self{
		if (!isset($this->type) || !$type->equals($this->type)) {
			$this->type = $type;
			$this->recalculateTypeProperties();
		}

		return $this;
	}

	/**
	 * @phpstan-return Closure(Living) : bool
	 */
	public function getAttackableValidator() : Closure{
		return $this->attackableValidator;
	}

	public function getJumpDelay() : int{
		return $this->random->nextBoundedInt(20) + 10;
	}

	public function jump() : void{
		$this->jumping = true;
		$this->networkPropertiesDirty = true;

		parent::jump();
	}

	public function isJumping() : bool{
		return $this->jumping;
	}

	protected function onHitGround() : ?float{
		$this->jumping = false;
		$this->networkPropertiesDirty = true;

		return parent::onHitGround();
	}

	protected function onDeathUpdate(int $tickDiff) : bool{
		$shouldDespawn = parent::onDeathUpdate($tickDiff);
		if ($shouldDespawn && ($slimeType = $this->type->getSplitType()) !== null) {
			$persistent = $this->isPersistent();

			$splitAmount = 2 + $this->random->nextBoundedInt(3);
			for ($i = 0; $i < $splitAmount; $i++) {
				$slime = new Slime($this->getLocation());
				$slime->setType($slimeType);
				$slime->setPersistent($persistent);
				$slime->spawnToAll();
			}
		}

		return $shouldDespawn;
	}

	public function getDrops() : array{
		$dropsGenerator = $this->type->getDropsGenerator();
		if ($dropsGenerator !== null) {
			return $dropsGenerator();
		}

		return [];
	}

	public function getXpDropAmount() : int{ //TODO!
		if ($this->hasBeenDamagedByPlayer()) {
			return (int) $this->type->getScale();
		}

		return 0;
	}
}
