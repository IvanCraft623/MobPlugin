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

use Closure;

use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Utils as PMUtils;
use pocketmine\world\World;
use function exp;
use function pow;

trait ItemPickupCapableTrait {

	protected const TAG_CAN_PICK_UP_ITEMS_JAVA = "CanPickUpLoot"; //TAG_Byte
	protected const TAG_CAN_PICK_UP_ITEMS_BEDROCK = "canPickupItems"; //TAG_Byte

	protected bool $canPickUpItems = false;

	public function canPickUpItems() : bool {
		return $this->canPickUpItems;
	}

	/**
	 * @return $this
	 */
	public function setCanPickUpItems(bool $value = true) : self{
		if ($this->canPickUpItems !== $value) {
			$this->canPickUpItems = $value;
			$this->updatePickupItemsGoal($value);
		}

		return $this;
	}

	protected function initEntity(CompoundTag $nbt) : void {
		if ($nbt->count() !== 0) {
			$this->setCanPickUpItems(
				$nbt->getByte(self::TAG_CAN_PICK_UP_ITEMS_JAVA, 0) !== 0 ||
				$nbt->getByte(self::TAG_CAN_PICK_UP_ITEMS_BEDROCK, 0) !== 0
			);
		} else { // First spawn
			/* Pickup probability, real formula is lot more complicated, it uses world difficulty,
			 * time played in the world, time played in the chunk and moon phase.
			 * It's too much work for a single feauture so let's simplify it. :D
			 */
			$world = $this->getWorld();
			$difficulty = $world->getDifficulty();
			if ($difficulty >= World::DIFFICULTY_NORMAL) {
				$timeOfDay = $world->getTimeOfDay();

				$maxPeak = 0.95;
				$floorMin = 0.50;
				$curveWidth = 2500;

				$gaussianFactor = exp(-pow($timeOfDay - World::TIME_MIDNIGHT, 2) / (2 * pow($curveWidth, 2)));
				$timeOfDayFactor = $floorMin + ($maxPeak - $floorMin) * $gaussianFactor;

				$this->setCanPickUpItems(PMUtils::getRandomFloat() <= (
					0.55 * ($difficulty / World::DIFFICULTY_HARD) * $timeOfDayFactor
				));
			}
		}
	}

	public function saveNBT() : CompoundTag {
		$nbt = CompoundTag::create();

		$nbt->setByte(self::TAG_CAN_PICK_UP_ITEMS_BEDROCK, $this->canPickUpItems ? 1 : 0);

		return $nbt;
	}

	abstract public function getWorld() : World;
	abstract protected function updatePickupItemsGoal(bool $enabled) : void;
	abstract public function pickupItem(ItemEntity $entity, int $count) : void;
	abstract public function getEquipInventoryAndSlot(Item $item, int &$slot) : Inventory;

	/**
	 * @phpstan-return array<int, int|Closure(Item): int>
	 */
	abstract public static function getWantedItems() : array;

}
