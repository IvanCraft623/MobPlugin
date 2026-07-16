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

namespace IvanCraft623\MobPlugin\entity\monster\skeleton;

use Closure;

use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

class Stray extends AbstractSkeleton { //uhh, maybe this should extend skeleton instead

	public static function getNetworkTypeId() : string {
		return EntityIds::STRAY;
	}

	public function getName() : string {
		return "Stray";
	}

	public function isSunSensitive() : bool{
		return true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		if ($nbt->count() === 0) {
			//TODO gear equipment
			$this->inventory->setItemInHand(VanillaItems::BOW());
		}
	}

	public function getDrops() : array{
		$drops = parent::getDrops();

		//TODO: looting enchantment logic
		$drops[] = VanillaItems::BONE()->setCount(mt_rand(0, 2));

		if ($this->hasBeenDamagedByPlayer()) {
			$drops[] = VanillaItems::ARROW()->setCount(mt_rand(0, 2));
		}

		return $drops;
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::STRAY_SPAWN_EGG();
	}

	/**
	 * @phpstan-return array<int, int|Closure(Item): int>
	 */
	public static function getWantedItems() : array {
		return Skeleton::getWantedItems();
	}

	//TODO: Arrows must have a slowness effect.
}
