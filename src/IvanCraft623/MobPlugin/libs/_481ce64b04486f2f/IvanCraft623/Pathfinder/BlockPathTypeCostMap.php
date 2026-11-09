<?php

/*
 *  _____      _   _      __ _           _
 * |  __ \    | | | |    / _(_)         | |
 * | |__) |_ _| |_| |__ | |_ _ _ __   __| | ___ _ __
 * |  ___/ _` | __| '_ \|  _| | '_ \ / _` |/ _ \ '__|
 * | |  | (_| | |_| | | | | | | | | | (_| |  __/ |
 * |_|   \__,_|\__|_| |_|_| |_|_| |_|\__,_|\___|_|
 *
 * A PocketMine-MP virion that implements a mob-oriented pathfinding.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder;

final class BlockPathTypeCostMap {

	/** @var array<string, float> */
	private array $pathfindingMalus = [];

	/**
	 * Costs of mobs that swim through water and walk on land.
	 */
	public static function amphibious() : self{
		$costMap = new self();
		$costMap->setPathfindingMalus(BlockPathType::WATER, 0.0);
		$costMap->setPathfindingMalus(BlockPathType::WALKABLE, 6.0);
		$costMap->setPathfindingMalus(BlockPathType::WATER_BORDER, 4.0);
		return $costMap;
	}

	public function getPathfindingMalus(BlockPathType $pathType) : float{
		return $this->pathfindingMalus[$pathType->name] ?? $pathType->getMalus();
	}

	public function setPathfindingMalus(BlockPathType $pathType, float $malus) : void{
		$this->pathfindingMalus[$pathType->name] = $malus;
	}

	/**
	 * A new map with these costs, overwritten by the ones $overrides sets.
	 */
	public function merge(BlockPathTypeCostMap $overrides) : self{
		$merged = new self();
		$merged->pathfindingMalus = $overrides->pathfindingMalus + $this->pathfindingMalus;
		return $merged;
	}
}