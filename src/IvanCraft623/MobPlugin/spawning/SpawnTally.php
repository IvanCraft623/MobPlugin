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

use pocketmine\math\Vector3;

/**
 * Mobs spawned during one apply pass, so later requests in the same pass count them
 * against population caps and density limits without rescanning the world. Scoped like
 * the census: same world, same band, within the population region radius.
 */
final class SpawnTally{
	/** Radius (blocks) approximating the 9×9 chunk population region. */
	public const REGION_RADIUS = 72;

	/** @phpstan-var array<string, list<Vector3>> */
	private array $byCategory = [];

	/** @phpstan-var array<string, list<Vector3>> */
	private array $byIdentifier = [];

	public function record(int $worldId, string $identifier, string $categoryId, SpawnBand $band, Vector3 $pos) : void{
		$this->byCategory[self::key($worldId, $categoryId, $band)][] = $pos;
		$this->byIdentifier[self::key($worldId, $identifier, $band)][] = $pos;
	}

	public function countCategory(int $worldId, string $categoryId, SpawnBand $band, Vector3 $center) : int{
		return self::countNear($this->byCategory[self::key($worldId, $categoryId, $band)] ?? [], $center);
	}

	public function countIdentifier(int $worldId, string $identifier, SpawnBand $band, Vector3 $center) : int{
		return self::countNear($this->byIdentifier[self::key($worldId, $identifier, $band)] ?? [], $center);
	}

	private static function key(int $worldId, string $id, SpawnBand $band) : string{
		return $worldId . "|" . $id . "|" . $band->name;
	}

	/**
	 * @phpstan-param list<Vector3> $positions
	 */
	private static function countNear(array $positions, Vector3 $center) : int{
		$radiusSquared = self::REGION_RADIUS ** 2;
		$count = 0;
		foreach($positions as $pos){
			if($pos->distanceSquared($center) <= $radiusSquared){
				$count++;
			}
		}

		return $count;
	}
}
