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

namespace IvanCraft623\MobPlugin\spawning\condition\vanilla\slime;

/**
 * Slime-chunk check for Bedrock: seed = (chunkX * 0x1f1f1f1f) XOR chunkZ (32-bit), then
 * the first MT19937 output; the chunk is slimy when that value divides by 10 exactly.
 * Reverse engineered by @protolambda and @jocopa3 (see slime-finder-pe lib/chunk.ts).
 */
final class SlimeChunkChecker{
	private const DENOMINATOR = 10;

	/**
	 * Memoized per-chunk results. The slime property of a chunk is fixed for the life of
	 * the world, but it is queried on every cave spawn attempt below Y 40 — rebuilding the
	 * Mersenne twister per call was the dominant Evaluate cost. Computing it once per chunk
	 * (the entity simulation area is bounded) turns the hot path into an associative-array
	 * lookup.
	 *
	 * @phpstan-var array<int, array<int, bool>>
	 */
	private static array $cache = [];

	public static function isSlimeChunk(int $chunkX, int $chunkZ) : bool{
		if(isset(self::$cache[$chunkX])){
			$byZ = self::$cache[$chunkX];
			if(isset($byZ[$chunkZ])){
				return $byZ[$chunkZ];
			}
		}
		$result = self::compute($chunkX, $chunkZ);
		if(isset(self::$cache[$chunkX])){
			self::$cache[$chunkX][$chunkZ] = $result;
		}else{
			self::$cache[$chunkX] = [$chunkZ => $result];
		}

		return $result;
	}

	private static function compute(int $chunkX, int $chunkZ) : bool{
		$seed = self::mul32($chunkX & 0xFFFFFFFF, 0x1f1f1f1f) ^ ($chunkZ & 0xFFFFFFFF);
		$twister = new MersenneTwister($seed & 0xFFFFFFFF);

		return ($twister->randomInt() % self::DENOMINATOR) === 0;
	}

	/**
	 * Low 32 bits of a * b over unsigned 32-bit operands (PHP ints are 64-bit, so the
	 * product fits and only the mask is needed).
	 */
	private static function mul32(int $a, int $b) : int{
		return ($a * $b) & 0xFFFFFFFF;
	}
}
