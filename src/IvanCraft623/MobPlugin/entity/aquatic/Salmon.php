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
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Salmon extends AbstractFish {

	protected SalmonSize $salmonSize;

	private static ?FlockingParameters $flockingParameters = null;

	public static function getNetworkTypeId() : string{ return EntityIds::SALMON; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::SALMON_HEIGHT, VanillaEntitySizes::SALMON_WIDTH);
	}

	public function getName() : string{
		return "Salmon";
	}

	public function getFlockingParameters() : FlockingParameters{
		return self::$flockingParameters ??= new FlockingParameters(
			influenceRadius: 3,
			speed: 0.12,
			separationWeight: 0.65,
			separationThreshold: 0.15,
			cohesionWeight: 2.25,
			innerCohesionThreshold: 1.5,
			goalWeight: 2,
			blockWeight: 0.75,
			blockDistance: 1,
			staysBelowSurface: true,
			useCenterOfMass: false,
			lowFlockLimit: 4,
			highFlockLimit: 8,
			lonerChance: 0.1
		);
	}

	protected function getAvoidDistance() : float{
		return 3;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$size = null;
		foreach(SalmonSize::cases() as $case){
			if($this->componentGroups->has($case->getComponentGroup())){
				$size = $case;
				break;
			}
		}
		$this->setSalmonSize($size ?? SalmonSize::random());
	}

	public function saveNBT() : CompoundTag{
		$this->componentGroups->add($this->salmonSize->getComponentGroup());

		return parent::saveNBT();
	}

	public function getSalmonSize() : SalmonSize{
		return $this->salmonSize;
	}

	/**
	 * @return $this
	 */
	public function setSalmonSize(SalmonSize $size) : self{
		$this->salmonSize = $size;
		$this->setScale($size->getScale());

		return $this;
	}

	public function getDrops() : array{
		return $this->generateDeathLoot($this->salmonSize === SalmonSize::LARGE ? VanillaLootTables::SALMON_LARGE() : VanillaLootTables::SALMON_NORMAL());
	}

	public function getBucketItem() : MobBucket{
		return ExtraVanillaItems::SALMON_BUCKET();
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::SALMON_SPAWN_EGG();
	}
}
