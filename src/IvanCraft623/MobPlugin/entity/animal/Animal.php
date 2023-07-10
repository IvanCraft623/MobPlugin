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
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\BlockLegacyIds;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\utils\Binary;
use pocketmine\world\particle\HeartParticle;
use function lcg_value;

abstract class Animal extends AgeableMob {

	private const TAG_IN_LOVE_TICKS = "InLove"; //TAG_Int

	protected const PARENT_AGE_AFTER_BREEDING = 6000;

	private int $inLoveTicks = 0;
	private ?Player $loveCauser = null;

	protected function initProperties() : void{
		parent::initProperties();

		$this->setPathfindingMalus(BlockPathTypes::DANGER_FIRE(), 16);
		$this->setPathfindingMalus(BlockPathTypes::DAMAGE_FIRE(), -1);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->setAge(Binary::unsignInt($nbt->getInt(self::TAG_IN_LOVE_TICKS, 0)));
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
				$width = $this->size->getWidth();
				for ($i = 0; $i < 3; $i++) {
					$this->getWorld()->addParticle(new Vector3(
						$this->location->x + $width * (2 * lcg_value() - 1),
						$this->location->y + $this->size->getHeight() * lcg_value(),
						$this->location->z + $width * (2 * lcg_value() - 1)
					), new HeartParticle()); //TODO: Heart particle scale
				}
			}
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if (!$source->isCancelled()) {
			$this->inLoveTicks = 0;
		}
	}

	public function getWalkTargetValue(Vector3 $position) : float{
		return $this->getWorld()->getBlock($position)->getId() === BlockLegacyIds::GRASS ? 10 : 0;
		//TODO: If it is not grass calculate the value using light level
	}

	//TODO: natural spawning logic

	public function getAmbientSoundInterval() : float{
		return 12;
	}

	public function shouldDespawnWhenFarAway(float $distanceSquared) : bool{
		return false;
	}

	public function getXpDropAmount() : int{
		return 1 + $this->random->nextBoundedInt(3); //TODO: check out this
	}

	public function isFood(Item $item) : bool {
		return $item->getId() === ItemIds::WHEAT;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if ($this->isFood($item)) {
			$age = $this->getAge();
			if ($age === AgeableMob::ADULT_AGE && $this->canFallInLove()) {
				Utils::popItemInHand($player);
				$this->setInLove($player);

				return true;
			}

			if ($this->isBaby()) {
				Utils::popItemInHand($player);
				$this->ageUp(static::getSpeedUpSecondsWhenFeeding(-$age), true);

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
			$offspring->spawnToAll();

			$this->finalizeSpawnChildFromBreeding($partner, $offspring);
		}
	}

	public function finalizeSpawnChildFromBreeding(Animal $partner, ?AgeableMob $offspring) : void{
		foreach ([$this, $partner] as $parent) {
			$parent->setAge(self::PARENT_AGE_AFTER_BREEDING);
			$parent->setInLoveTicks(0);
		}

		$this->getWorld()->dropExperience($this->location, $this->random->nextBoundedInt(7) + 1);
	}
}
