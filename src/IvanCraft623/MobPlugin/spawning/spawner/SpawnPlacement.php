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

namespace IvanCraft623\MobPlugin\spawning\spawner;

use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use pocketmine\block\utils\SupportType;
use pocketmine\math\Facing;
use pocketmine\world\World;
use function count;

/**
 * Where the ground is and whether a mob fits, for one world during one pass. Sampling,
 * band classification and herd placement all share it, so they always agree.
 */
final class SpawnPlacement{
	/** @phpstan-var array<int, int> column key => ground Y */
	private array $groundY = [];

	public function __construct(
		private readonly World $world
	){}

	public function getWorld() : World{
		return $this->world;
	}

	/**
	 * The highest block with a full top surface scanning down from the column top, so air,
	 * liquids and canopies are skipped. Falls back to the column top when there is none.
	 */
	public function getGroundY(int $x, int $z) : int{
		$key = ($x << 32) | ($z & 0xFFFFFFFF);
		if(isset($this->groundY[$key])){
			return $this->groundY[$key];
		}

		$minY = $this->world->getMinY();
		$topY = $this->world->getHighestBlockAt($x, $z) ?? $minY;
		$groundY = $topY;
		for($y = $topY; $y >= $minY; $y--){
			if($this->world->getBlockAt($x, $y, $z, addToCache: false)->getSupportType(Facing::UP) === SupportType::FULL){
				$groundY = $y;
				break;
			}
		}

		return $this->groundY[$key] = $groundY;
	}

	/**
	 * The head goes in a block with no collision boxes. Land mobs need the same of their
	 * non-liquid feet, over a block with a full top surface. Aquatic mobs need the liquid
	 * at the feet, but no ground.
	 */
	public function hasRoom(int $x, int $y, int $z, SpawnLiquid $requiredLiquid = SpawnLiquid::NONE) : bool{
		if(count($this->world->getBlockAt($x, $y + 1, $z)->getCollisionBoxes()) !== 0){
			return false;
		}
		$feet = $this->world->getBlockAt($x, $y, $z);
		$feetLiquid = SpawnLiquid::fromBlockTypeId($feet->getTypeId());
		if($requiredLiquid !== SpawnLiquid::NONE){
			return $feetLiquid === $requiredLiquid;
		}
		if(count($feet->getCollisionBoxes()) !== 0 || $feetLiquid !== SpawnLiquid::NONE){
			return false;
		}

		return $this->world->getBlockAt($x, $y - 1, $z)->getSupportType(Facing::UP) === SupportType::FULL;
	}

	public function clear() : void{
		$this->groundY = [];
	}
}
