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
use PHPUnit\Framework\TestCase;

/**
 * Same-pass spawns count only where the census would count them: same world, same band,
 * inside the population region.
 */
final class SpawnTallyTest extends TestCase{

	public function testCountsAreScopedByWorldBandAndRadius() : void{
		$tally = new SpawnTally();
		$origin = new Vector3(0, 64, 0);
		$tally->record(1, "minecraft:cow", "animal", SpawnBand::SURFACE, $origin);
		$tally->record(1, "minecraft:cow", "animal", SpawnBand::SURFACE, new Vector3(3, 64, 0));

		self::assertSame(2, $tally->countCategory(1, "animal", SpawnBand::SURFACE, $origin));
		self::assertSame(2, $tally->countIdentifier(1, "minecraft:cow", SpawnBand::SURFACE, $origin));

		self::assertSame(0, $tally->countCategory(2, "animal", SpawnBand::SURFACE, $origin), "other world");
		self::assertSame(0, $tally->countCategory(1, "animal", SpawnBand::CAVE, $origin), "other band");
		self::assertSame(0, $tally->countCategory(1, "monster", SpawnBand::SURFACE, $origin), "other category");
		self::assertSame(0, $tally->countIdentifier(1, "minecraft:pig", SpawnBand::SURFACE, $origin), "other identifier");

		$far = new Vector3(SpawnTally::REGION_RADIUS + 10, 64, 0);
		self::assertSame(0, $tally->countCategory(1, "animal", SpawnBand::SURFACE, $far), "outside the region");
	}
}
