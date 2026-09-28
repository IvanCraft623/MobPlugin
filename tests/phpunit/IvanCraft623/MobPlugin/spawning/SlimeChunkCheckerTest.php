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

use IvanCraft623\MobPlugin\spawning\condition\vanilla\slime\MersenneTwister;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\slime\SlimeChunkChecker;
use PHPUnit\Framework\TestCase;

/**
 * Guards the slime-chunk memoization: SlimeChunkChecker::isSlimeChunk() caches per-chunk
 * results, so it must always agree with the raw MersenneTwister reference computation and
 * be stable across repeated calls (the cache must not desync).
 */
final class SlimeChunkCheckerTest extends TestCase{

	public function testMemoizedMatchesReferenceOverGrid() : void{
		for($chunkX = -8; $chunkX <= 8; $chunkX++){
			for($chunkZ = -8; $chunkZ <= 8; $chunkZ++){
				$expected = self::reference($chunkX, $chunkZ);
				self::assertSame($expected, SlimeChunkChecker::isSlimeChunk($chunkX, $chunkZ), "chunk ($chunkX, $chunkZ)");
				// Second call hits the cache — must give the identical result.
				self::assertSame($expected, SlimeChunkChecker::isSlimeChunk($chunkX, $chunkZ), "chunk ($chunkX, $chunkZ) cached");
			}
		}
	}

	/** The reference computation, exactly as the checker did before memoization. */
	private static function reference(int $chunkX, int $chunkZ) : bool{
		$seed = (($chunkX & 0xFFFFFFFF) * 0x1f1f1f1f & 0xFFFFFFFF) ^ ($chunkZ & 0xFFFFFFFF);
		$twister = new MersenneTwister($seed & 0xFFFFFFFF);

		return ($twister->randomInt() % 10) === 0;
	}
}