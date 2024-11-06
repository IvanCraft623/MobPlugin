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

use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\animation\BabyAnimalFeedAnimation;
use IvanCraft623\MobPlugin\entity\animation\BreedingAnimation;
use IvanCraft623\MobPlugin\entity\animation\ConsumingItemAnimation;
use IvanCraft623\MobPlugin\utils\Utils;
use IvanCraft623\Pathfinder\BlockPathType;
use pocketmine\block\BlockTypeIds;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\utils\Binary;
use function mt_rand;

abstract class Animal extends AgeableMob {

	private const TAG_IN_LOVE_TICKS = "InLove"; //TAG_Int

	protected const PARENT_AGE_AFTER_BREEDING = 6000;

	private int $inLoveTicks = 0;
	private ?Player $loveCauser = null;

	protected function initProperties() : void{
		parent::initProperties();

		$this->setPathfindingMalus(BlockPathType::DANGER_FIRE, 16);
		$this->setPathfindingMalus(BlockPathType::DAMAGE_FIRE, -1);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->inLoveTicks = $nbt->getInt(self::TAG_IN_LOVE_TICKS, 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_IN_LOVE_TICKS, Binary::signInt($this->inLoveTicks));

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::INLOVE, $this->inLoveTicks > 0);
	}

	public function tickAi() : void{
		parent::tickAi();

		if ($this->getAge() !== AgeableMob::ADULT_AGE) {
			$this->inLoveTicks = 0;
		}

		if ($this->inLoveTicks > 0) {
			$this->inLoveTicks--;

			if ($this->inLoveTicks % 16 === 0) {
				$this->broadcastAnimation(new BreedingAnimation($this));
			}
		}
	}

	public function getWalkTargetValue(Vector3 $position) : float{
		return $this->getWorld()->getBlock($position)->getTypeId() === BlockTypeIds::GRASS ? 10 : 0;
		//TODO: If it is not grass calculate the value using light level
	}

	//TODO: natural spawning logic

	public function getAmbientSoundInterval() : float{
		return 12;
	}

	public function getXpDropAmount() : int{
		if (!$this->isBaby() && $this->hasBeenDamagedByPlayer()) {
			return mt_rand(1, 3);
		}

		return 0;
	}

	public function isFood(Item $item) : bool {
		return $item->getTypeId() === ItemTypeIds::WHEAT;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if ($this->isFood($item)) {
			$age = $this->getAge();
			if ($age === AgeableMob::ADULT_AGE && $this->canFallInLove()) {
				Utils::popItemInHand($player);
				$this->setInLove($player);
				$this->setPersistent();

				$this->broadcastAnimation(new ConsumingItemAnimation($this, $item));

				return true;
			}

			if ($this->isBaby()) {
				Utils::popItemInHand($player);
				$this->ageUp(static::getAgeUpWhenFeeding($age));
				$this->setPersistent();

				$this->broadcastAnimation(new BabyAnimalFeedAnimation($this));

				return true;
			}
		}

		return parent::onInteract($player, $clickPos);
	}

	public function canFallInLove() : bool {
		return $this->inLoveTicks <= 0;
	}

	public function setInLove(?Player $player = null) : void {
		$this->inLoveTicks = 600;
		$this->loveCauser = $player;

		$this->networkPropertiesDirty = true;
	}

	public function isInLove() : bool{
		return $this->inLoveTicks > 0;
	}

	public function setInLoveTicks(int $ticks) : void{
		$inLove = $this->isInLove();
		if ($inLove && $ticks <= 0 || !$inLove && $ticks > 0) {
			$this->networkPropertiesDirty = true;
		}

		$this->inLoveTicks = $ticks;
	}

	public function getInLoveTicks() : int {
		return $this->inLoveTicks;
	}

	public function getLoveCauser() : ?Player {
		return $this->loveCauser;
	}

	public function canMate(Animal $other) : bool{
		if ($other === $this) {
			return false;
		}
		if ($other::class !== $this::class) {
			return false;
		}

		return $this->isInLove() && $other->isInLove();
	}

	public function spawnChildFromBreeding(Animal $partner) : void{
		$offspring = $this->getBreedOffspring($partner);
		if ($offspring !== null) {
			$offspring->setBaby();
			$offspring->setPersistent();
			$offspring->spawnToAll();

			$this->finalizeSpawnChildFromBreeding($partner, $offspring);
		}
	}

	public function finalizeSpawnChildFromBreeding(Animal $partner, AgeableMob $offspring) : void{
		foreach ([$this, $partner] as $parent) {
			$parent->setAge(self::PARENT_AGE_AFTER_BREEDING);
			$parent->setInLoveTicks(0);
		}

		$this->getWorld()->dropExperience($this->location, $this->random->nextBoundedInt(7) + 1);
	}
}
