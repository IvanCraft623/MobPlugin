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
use PHPUnit\Framework\TestCase;

final class SpawnPlacementTest extends TestCase{
	use FakeWorldTrait;

	public function testGroundSkipsWaterAndCanopy() : void{
		$this->waterTopY["1:1"] = 70;
		$this->canopyY["2:2"] = 75;
		$placement = new SpawnPlacement($this->createWorld());

		self::assertSame(63, $placement->getGroundY(0, 0));
		self::assertSame(63, $placement->getGroundY(1, 1), "ocean floor, not the water surface");
		self::assertSame(63, $placement->getGroundY(2, 2), "forest floor, not the leaves");
	}

	public function testGroundIsMemoizedUntilCleared() : void{
		$placement = new SpawnPlacement($this->createWorld());
		self::assertSame(63, $placement->getGroundY(5, -5));
		$reads = $this->blockReads;

		$this->groundY["5:-5"] = 80; // e.g. a factory built on the column
		self::assertSame(63, $placement->getGroundY(5, -5));
		self::assertSame($reads, $this->blockReads, "a memo hit reads no blocks");

		$placement->clear();
		self::assertSame(80, $placement->getGroundY(5, -5));
	}

	public function testRoom() : void{
		$this->waterTopY["1:0"] = 70;
		$placement = new SpawnPlacement($this->createWorld());

		self::assertTrue($placement->hasRoom(0, 64, 0));
		self::assertFalse($placement->hasRoom(0, 63, 0), "feet inside the ground");
		self::assertFalse($placement->hasRoom(0, 70, 0), "no ground under the feet");
		self::assertFalse($placement->hasRoom(1, 64, 0), "land mob in water");
		self::assertTrue($placement->hasRoom(1, 66, 0, SpawnLiquid::WATER), "aquatic mobs need no ground");
		self::assertFalse($placement->hasRoom(0, 64, 0, SpawnLiquid::WATER));
		self::assertFalse($placement->hasRoom(1, 64, 0, SpawnLiquid::LAVA));
	}
}
