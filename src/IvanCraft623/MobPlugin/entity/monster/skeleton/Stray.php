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

use IvanCraft623\MobPlugin\entity\ai\goal\AvoidEntityGoal;
use IvanCraft623\MobPlugin\entity\animal\Wolf;
use IvanCraft623\MobPlugin\entity\projectile\EffectArrow;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Stray extends AbstractSkeleton { //uhh, maybe this should extend skeleton instead

	private const SLOWNESS_TICKS = 30 * 20;
	private const SLOWNESS_ARROW_AUX_VALUE = 19;

	public static function getNetworkTypeId() : string {
		return EntityIds::STRAY;
	}

	protected function registerGoals() : void{
		parent::registerGoals();

		$this->goalSelector->addGoal(4, new AvoidEntityGoal($this, [Wolf::class], 6, 1.2, 1.2));
	}

	public function getName() : string {
		return "Stray";
	}

	public function isSunSensitive() : bool{
		return true;
	}

	protected function createArrow(Location $location) : Arrow{
		return new EffectArrow(
			$location,
			$this,
			false,
			[new EffectInstance(VanillaEffects::SLOWNESS(), self::SLOWNESS_TICKS)],
			self::SLOWNESS_ARROW_AUX_VALUE
		);
	}

	public function generateEquipment() : void{
		$this->equipFromLootTable(VanillaLootTables::SKELETON_GEAR());
	}

	public function getDrops() : array{
		return [...$this->getEquipmentDrops(), ...$this->generateDeathLoot(VanillaLootTables::STRAY())];
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
}
