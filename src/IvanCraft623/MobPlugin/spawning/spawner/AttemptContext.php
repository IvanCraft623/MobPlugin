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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\population\PopulationCensus;
use IvanCraft623\MobPlugin\spawning\population\PopulationCounts;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use pocketmine\block\Block;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\math\AxisAlignedBB;
use pocketmine\utils\Random;
use pocketmine\world\World;
use function ceil;
use function count;
use function morton2d_encode;

/**
 * One sampled position. Light and population are read on first use only, so positions
 * no rule can use never pay for them.
 */
final class AttemptContext implements SpawnConditionContext{
	private const ROOM_INSET = 1e-7;

	/**
	 * Every collision box coordinate of every block state is a multiple of 1/1600
	 * of a block (pixels are 1/16), so sizes that reach the same unit collide alike.
	 */
	private const COLLISION_UNITS_PER_BLOCK = 1600;

	private ?int $light = null;

	private ?int $blockLight = null;

	private ?PopulationCounts $population = null;

	private ?int $belowItemStateId = null;

	/** @phpstan-var array<int, bool> size hash => whether a box of that size fits */
	private array $roomBySize = [];

	public function __construct(
		private readonly World $world,
		private readonly PopulationCensus $census,
		private readonly int $x,
		private readonly int $y,
		private readonly int $z,
		private readonly SpawnBand $band,
		private readonly int $biomeId,
		private readonly SpawnLiquid $feetLiquid,
		private readonly Block $below,
		private readonly int $difficulty,
		private readonly float $nearestPlayerDistance,
		private readonly int $time,
		private readonly Random $random
	){}

	public function getBiomeId() : int{
		return $this->biomeId;
	}

	public function getBand() : SpawnBand{
		return $this->band;
	}

	public function getDifficulty() : int{
		return $this->difficulty;
	}

	public function getFeetLiquid() : SpawnLiquid{
		return $this->feetLiquid;
	}

	public function getX() : int{
		return $this->x;
	}

	public function getY() : int{
		return $this->y;
	}

	public function getZ() : int{
		return $this->z;
	}

	public function getLight() : int{
		return $this->light ??= $this->world->getFullLightAt($this->x, $this->y, $this->z);
	}

	public function getBlockLight() : int{
		return $this->blockLight ??= $this->world->getBlockLightAt($this->x, $this->y, $this->z);
	}

	public function getBelowItemStateId() : int{
		return $this->belowItemStateId ??= $this->below->asItem()->getStateId();
	}

	public function getNearestPlayerDistance() : float{
		return $this->nearestPlayerDistance;
	}

	public function getTime() : int{
		return $this->time;
	}

	public function getPopulation() : PopulationCounts{
		return $this->population ??= $this->census->getRegionPopulation($this->x >> 4, $this->z >> 4);
	}

	public function hasRoomFor(EntitySizeInfo $size) : bool{
		// Many candidates at one position share a size.
		$hash = morton2d_encode(
			(int) ceil($size->getWidth() / 2 * self::COLLISION_UNITS_PER_BLOCK),
			(int) ceil($size->getHeight() * self::COLLISION_UNITS_PER_BLOCK)
		);
		if(isset($this->roomBySize[$hash])){
			return $this->roomBySize[$hash];
		}

		$halfWidth = $size->getWidth() / 2;
		$centerX = $this->x + 0.5;
		$centerZ = $this->z + 0.5;
		$box = (new AxisAlignedBB(
			$centerX - $halfWidth,
			$this->y,
			$centerZ - $halfWidth,
			$centerX + $halfWidth,
			$this->y + $size->getHeight(),
			$centerZ + $halfWidth
		))->contract(self::ROOM_INSET, self::ROOM_INSET, self::ROOM_INSET);

		return $this->roomBySize[$hash] = count($this->world->getCollisionBlocks($box, targetFirst: true)) === 0;
	}

	public function getRandom() : Random{
		return $this->random;
	}

	public function getWorld() : World{
		return $this->world;
	}
}