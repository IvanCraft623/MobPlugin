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

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\FlockingParameters;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\item\MobBucket;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Cod extends AbstractFish {

	private static ?FlockingParameters $flockingParameters = null;

	public static function getNetworkTypeId() : string{ return EntityIds::COD; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::COD_HEIGHT, VanillaEntitySizes::COD_WIDTH);
	}

	public function getName() : string{
		return "Cod";
	}

	protected function getAvoidDistance() : float{
		return 6;
	}

	public function getFlockingParameters() : FlockingParameters{
		return self::$flockingParameters ??= new FlockingParameters(
			influenceRadius: 3,
			speed: 0.09,
			separationWeight: 1.75,
			separationThreshold: 0.95,
			cohesionWeight: 2,
			innerCohesionThreshold: 1.25,
			goalWeight: 2,
			blockWeight: 0.85,
			blockDistance: 2,
			staysBelowSurface: true,
			useCenterOfMass: true,
			lowFlockLimit: 4,
			highFlockLimit: 8,
			lonerChance: 0.1
		);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.1;
	}

	public function getDrops() : array{
		return $this->generateDeathLoot(VanillaLootTables::FISH());
	}

	public function getBucketItem() : MobBucket{
		return ExtraVanillaItems::COD_BUCKET();
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::COD_SPAWN_EGG();
	}
}
