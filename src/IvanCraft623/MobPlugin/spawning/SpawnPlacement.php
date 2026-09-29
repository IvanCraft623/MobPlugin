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

namespace IvanCraft623\MobPlugin\spawning;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\world\World;

/**
 * The single definition of "where is the ground" and "can a mob stand here", shared by
 * the collector, the census and the applier so sampling, band classification and herd
 * placement always agree.
 */
final class SpawnPlacement{

	private function __construct(){}

	/**
	 * A block mobs can stand on: solid, full cube and opaque. Leaves, glass, slabs,
	 * fences and liquids are not.
	 */
	public static function isSpawnableGround(Block $block) : bool{
		return $block->isSolid() && $block->isFullCube() && !$block->isTransparent();
	}

	public static function isLiquid(int $blockTypeId) : bool{
		return $blockTypeId === BlockTypeIds::WATER || $blockTypeId === BlockTypeIds::LAVA;
	}

	/**
	 * Y of the column's spawnable ground: the highest spawnable-ground block scanning down
	 * from the column top, so air, liquids and canopies are skipped (an ocean's floor, not
	 * its water surface; the forest floor, not the leaves). Falls back to the column top
	 * (world minimum when empty) when the column has no spawnable ground.
	 */
	public static function groundY(World $world, int $x, int $z) : int{
		$minY = $world->getMinY();
		$topY = $world->getHighestBlockAt($x, $z) ?? $minY;
		for($y = $topY; $y >= $minY; $y--){
			if(self::isSpawnableGround($world->getBlockAt($x, $y, $z, false))){
				return $y;
			}
		}

		return $topY;
	}

	/**
	 * Whether a mob fits with its feet at the given block, against the live world.
	 *
	 * Land mobs (no required liquid) need passable, non-liquid feet and head cells over
	 * spawnable ground. Aquatic mobs (a required liquid) need that liquid at the feet and a
	 * passable head cell; they need no ground, so herd members can float mid-water.
	 */
	public static function hasRoom(World $world, int $x, int $y, int $z, ?int $requiredLiquid = null) : bool{
		if($world->getBlockAt($x, $y + 1, $z)->isSolid()){
			return false;
		}
		$feet = $world->getBlockAt($x, $y, $z);
		if($requiredLiquid !== null){
			return $feet->getTypeId() === $requiredLiquid;
		}
		if($feet->isSolid() || self::isLiquid($feet->getTypeId())){
			return false;
		}

		return self::isSpawnableGround($world->getBlockAt($x, $y - 1, $z));
	}
}
