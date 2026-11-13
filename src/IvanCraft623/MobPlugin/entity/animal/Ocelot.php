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

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\Trusting;
use IvanCraft623\MobPlugin\entity\TrustingTrait;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;

/**
 * Can't be tamed: fish makes a wild one trust players, so it stops avoiding them and can breed.
 */
class Ocelot extends Feline implements Trusting{
	use TrustingTrait{
		//syncNetworkData()
		TrustingTrait::syncNetworkData as protected syncTrusting;

		//onInteract()
		TrustingTrait::onInteract as protected interactTrusting;
	}

	private const COMPONENT_GROUP_TRUSTING = "minecraft:ocelot_trusting";
	private const COMPONENT_GROUP_WILD = "minecraft:ocelot_wild";

	private const MAX_HEALTH = 10; //health
	private const ATTACK_DAMAGE = 3; //health
	private const TRUST_CHANCE = 0.33; //probability per fish

	public static function getNetworkTypeId() : string{ return EntityIds::OCELOT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::OCELOT_HEIGHT, VanillaEntitySizes::OCELOT_WIDTH);
	}

	public function getName() : string{
		return "Ocelot";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(self::MAX_HEALTH);
		$this->setAttackDamage(self::ATTACK_DAMAGE);
	}

	protected function registerGoals() : void{
		parent::registerGoals();

		$this->targetSelector->addGoal(1, new NearestAttackableGoal(entity: $this, targetType: Chicken::class, range: 8));
		//TODO: Ocelots also hunt baby turtles, and an adult can spawn with two wild kittens!
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->readTrusting();
	}

	public function saveNBT() : CompoundTag{
		$this->writeTrusting();

		return parent::saveNBT();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$this->syncTrusting($properties);
	}

	public function isWary() : bool{
		return !$this->isTrusting();
	}

	protected function getTrustingComponentGroup() : string{
		return self::COMPONENT_GROUP_TRUSTING;
	}

	protected function getWaryComponentGroup() : string{
		return self::COMPONENT_GROUP_WILD;
	}

	protected function isTrustItem(Item $item) : bool{
		return $this->isFood($item);
	}

	protected function getTrustChance() : float{
		return self::TRUST_CHANCE;
	}

	public function canFallInLove() : bool{
		return $this->isTrusting() && parent::canFallInLove();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		//Fish earns a wild ocelot's trust, even a kitten's, so it comes before feeding
		return $this->interactTrusting($player, $clickPos) || parent::onInteract($player, $clickPos);
	}

	/**
	 * The kitten of a pair trusts players.
	 */
	public function getBreedOffspring(AgeableMob $partner) : Ocelot{
		$offspring = new Ocelot($this->getLocation());
		$offspring->setTrusting(true);

		return $offspring;
	}

	public function getDrops() : array{
		return $this->isBaby() ? [] : $this->generateDeathLoot(VanillaLootTables::OCELOT());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::OCELOT_SPAWN_EGG();
	}
}