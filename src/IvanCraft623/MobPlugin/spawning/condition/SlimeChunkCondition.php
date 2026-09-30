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

namespace IvanCraft623\MobPlugin\spawning\condition;

use function count;

/**
 * Bedrock slime chunks: seed = (chunkX * 0x1f1f1f1f) XOR chunkZ (32-bit), and the chunk is
 * slimy when the first MT19937 output divides by 10. No world seed is involved.
 * Reverse engineered by @protolambda and @jocopa3 (slime-finder-pe lib/chunk.ts).
 */
final class SlimeChunkCondition implements SpawnCondition{
	private const MT_M = 397;
	private const MT_MATRIX_A = 0x9908b0df;
	private const MT_UPPER_MASK = 0x80000000;
	private const MT_LOWER_MASK = 0x7fffffff;

	private const MEMO_LIMIT = 4096;

	/** @phpstan-var array<int, bool> chunk hash => result; a memo of a pure function */
	private static array $memo = [];

	public function isCacheable() : bool{
		return true;
	}

	public function test(SpawnConditionContext $ctx) : bool{
		return self::isSlimeChunk($ctx->getX() >> 4, $ctx->getZ() >> 4);
	}

	public static function isSlimeChunk(int $chunkX, int $chunkZ) : bool{
		$key = (($chunkX & 0xFFFFFFFF) << 32) | ($chunkZ & 0xFFFFFFFF);
		if(isset(self::$memo[$key])){
			return self::$memo[$key];
		}
		if(count(self::$memo) >= self::MEMO_LIMIT){
			self::$memo = [];
		}

		return self::$memo[$key] = self::getFirstOutput((($chunkX & 0xFFFFFFFF) * 0x1f1f1f1f & 0xFFFFFFFF) ^ ($chunkZ & 0xFFFFFFFF)) % 10 === 0;
	}

	/**
	 * First genrand_int32() after init_genrand($seed). The first twist only reads state
	 * words 0, 1 and M, so the init stops at M instead of filling all 624 words.
	 */
	private static function getFirstOutput(int $seed) : int{
		$first = $seed;
		$second = 0;
		$state = $seed;
		for($i = 1; $i <= self::MT_M; $i++){
			// 32-bit operands times a 31-bit constant fit in a 64-bit int.
			$state = (1812433253 * ($state ^ ($state >> 30)) + $i) & 0xFFFFFFFF;
			if($i === 1){
				$second = $state;
			}
		}

		$y = ($first & self::MT_UPPER_MASK) | ($second & self::MT_LOWER_MASK);
		$y = $state ^ ($y >> 1) ^ (($y & 1) === 1 ? self::MT_MATRIX_A : 0);

		$y ^= $y >> 11;
		$y ^= ($y << 7) & 0x9d2c5680;
		$y ^= ($y << 15) & 0xefc60000;
		$y ^= $y >> 18;

		return $y & 0xFFFFFFFF;
	}
}
