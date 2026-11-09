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

namespace IvanCraft623\MobPlugin\entity\aquatic;

use IvanCraft623\MobPlugin\data\bedrock\PuffStateIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\animal\Axolotl;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\MobType;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\item\MobBucket;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Squid;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

/**
 * Puffs up when something comes close and poisons what touches it.
 */
class Pufferfish extends AbstractFish {

	private const SENSE_INTERVAL = 4; //ticks between threat checks
	private const PUFF_RANGE = 2.5; //blocks
	private const DEFLATE_RANGE = 2.9; //blocks
	private const HALF_TICKS = 40;
	private const DEFLATE_TICKS = 60;

	private const STING_INTERVAL = 10; //ticks
	private const STING_REACH = 0.2; //blocks
	private const STING_DAMAGE = 2; //health points
	private const POISON_TICKS = 200;

	public static function getNetworkTypeId() : string{ return EntityIds::PUFFERFISH; }

	private PuffState $puffState = PuffState::NORMAL;

	/** Counts down the half puff, or the deflation of a full one. */
	private int $puffTicks = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::PUFFERFISH_HEIGHT, VanillaEntitySizes::PUFFERFISH_WIDTH);
	}

	public function getName() : string{
		return "Pufferfish";
	}

	protected function getAvoidDistance() : float{
		return 6;
	}

	public function getDefaultMovementSpeed() : float{
		return 0.13;
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setScale(1.2);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, PuffStateIdMap::getInstance()->toId($this->puffState));
	}

	protected function tickState() : void{
		parent::tickState();

		$sense = $this->ticksLived % self::SENSE_INTERVAL === 0;
		switch ($this->puffState){
			case PuffState::NORMAL:
				if ($sense && $this->senseThreat(self::PUFF_RANGE)){
					$this->setPuffState(PuffState::HALF, self::HALF_TICKS * 2);
				}
				break;
			case PuffState::HALF:
				//The second half is when it can still fill up
				if ($sense && $this->puffTicks <= self::HALF_TICKS && $this->senseThreat(self::PUFF_RANGE)){
					$this->setPuffState(PuffState::FULL);
				}elseif (--$this->puffTicks <= 0){
					$this->setPuffState(PuffState::NORMAL);
				}
				break;
			case PuffState::FULL:
				if ($this->puffTicks > 0){
					if (--$this->puffTicks === 0){
						$this->setPuffState(PuffState::HALF, self::HALF_TICKS);
					}
				}elseif ($sense && !$this->senseThreat(self::DEFLATE_RANGE)){
					$this->puffTicks = self::DEFLATE_TICKS;
				}
				if ($this->ticksLived % self::STING_INTERVAL === 0){
					$this->sting();
				}
				break;
		}
	}

	private function setPuffState(PuffState $state, int $ticks = 0) : void{
		$this->puffState = $state;
		$this->puffTicks = $ticks;
		$this->networkPropertiesDirty = true;
	}

	private function senseThreat(float $range) : bool{
		foreach ($this->getSensing()->getNearbyLiving($range) as $entity){
			if ($this->isThreat($entity)){
				return true;
			}
		}

		return false;
	}

	private function isThreat(Living $entity) : bool{
		return $entity instanceof Axolotl || !self::isAquatic($entity);
	}

	private static function isAquatic(Living $entity) : bool{
		return $entity instanceof Squid || ($entity instanceof Mob && $entity->getMobType() === MobType::WATER());
	}

	private function sting() : void{
		$box = $this->getBoundingBox()->expandedCopy(self::STING_REACH, self::STING_REACH, self::STING_REACH);
		foreach ($this->getWorld()->getNearbyEntities($box, $this) as $entity){
			if (!$entity instanceof Living || !$this->isThreat($entity) || ($entity instanceof Player && $entity->isCreative())){
				continue;
			}

			$entity->attack(new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_CONTACT, self::STING_DAMAGE));
			if (!($entity instanceof Mob && $entity->getMobType() === MobType::UNDEAD())){
				$entity->getEffects()->add(new EffectInstance(VanillaEffects::POISON(), self::POISON_TICKS));
			}
		}
	}

	public function getDrops() : array{
		return $this->generateDeathLoot(VanillaLootTables::PUFFERFISH());
	}

	public function getBucketItem() : MobBucket{
		return ExtraVanillaItems::PUFFERFISH_BUCKET();
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::PUFFERFISH_SPAWN_EGG();
	}
}