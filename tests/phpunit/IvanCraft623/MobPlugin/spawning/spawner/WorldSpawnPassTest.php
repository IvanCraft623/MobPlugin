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

use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;

final class WorldSpawnPassTest extends TestCase{

	public function testMobsCanStandInBlocksWithNothingToCollideWith() : void{
		self::assertTrue(WorldSpawnPass::isPassable(VanillaBlocks::AIR()));
		self::assertTrue(WorldSpawnPass::isPassable(VanillaBlocks::TALL_GRASS()));
		self::assertTrue(WorldSpawnPass::isPassable(VanillaBlocks::TORCH()));
		self::assertFalse(WorldSpawnPass::isPassable(VanillaBlocks::GRASS()));
		self::assertFalse(WorldSpawnPass::isPassable(VanillaBlocks::OAK_SLAB()));
	}

	/**
	 * Snowy biomes are covered in single snow layers, which PocketMine gives a flat
	 * collision box: rejecting them leaves the whole biome without surface spawns.
	 */
	public function testSingleSnowLayerIsPassableAndThickerSnowIsNot() : void{
		self::assertTrue(WorldSpawnPass::isPassable(VanillaBlocks::SNOW_LAYER()));
		self::assertFalse(WorldSpawnPass::isPassable(VanillaBlocks::SNOW_LAYER()->setLayers(2)));
	}
}
