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

use PHPUnit\Framework\TestCase;
use pocketmine\utils\Random;

final class SlimeChunkConditionTest extends TestCase{

	public function testMatchesFullMersenneTwisterReference() : void{
		for($chunkX = -40; $chunkX <= 40; $chunkX++){
			for($chunkZ = -40; $chunkZ <= 40; $chunkZ++){
				self::assertSame(self::reference($chunkX, $chunkZ), SlimeChunkCondition::isSlimeChunk($chunkX, $chunkZ), "chunk ($chunkX, $chunkZ)");
			}
		}
	}

	public function testExtremeChunkCoordinates() : void{
		foreach([[2 ** 27, -(2 ** 27)], [-(2 ** 31), 2 ** 31 - 1], [123456, -654321]] as [$chunkX, $chunkZ]){
			self::assertSame(self::reference($chunkX, $chunkZ), SlimeChunkCondition::isSlimeChunk($chunkX, $chunkZ));
		}
	}

	/**
	 * The last chunk's answer is kept: asking for others in between, the same one twice,
	 * or ones differing in a single coordinate must never return a stale answer.
	 */
	public function testRememberedChunkIsNeverStale() : void{
		$random = new Random(555);
		$chunks = [[0, 0], [0, 0], [0, 1], [1, 0], [0, 0], [-1, 0], [0, -1]];
		for($i = 0; $i < 400; $i++){
			$chunk = [$random->nextRange(-60, 60), $random->nextRange(-60, 60)];
			$chunks[] = $chunk;
			if($random->nextBoolean()){
				$chunks[] = $chunk;
			}
			$chunks[] = [$chunk[0], $chunk[1] + 1];
			$chunks[] = [$chunk[0] + 1, $chunk[1] + 1];
		}
		$slimy = 0;
		foreach($chunks as $index => [$chunkX, $chunkZ]){
			$expected = self::reference($chunkX, $chunkZ);
			self::assertSame($expected, SlimeChunkCondition::isSlimeChunk($chunkX, $chunkZ), "step $index, chunk ($chunkX, $chunkZ)");
			$slimy += $expected ? 1 : 0;
		}
		self::assertGreaterThan(50, $slimy, "both answers must be exercised");
	}

	/**
	 * Complete init_genrand + genrand_int32 (all 624 words twisted), as Bedrock runs it.
	 */
	private static function reference(int $chunkX, int $chunkZ) : bool{
		$seed = (($chunkX & 0xFFFFFFFF) * 0x1f1f1f1f & 0xFFFFFFFF) ^ ($chunkZ & 0xFFFFFFFF);

		$n = 624;
		$m = 397;
		$mt = [$seed];
		for($i = 1; $i < $n; $i++){
			$s = $mt[$i - 1] ^ ($mt[$i - 1] >> 30);
			$mt[$i] = ((((($s >> 16) & 0xFFFF) * 1812433253) << 16) + ($s & 0xFFFF) * 1812433253 + $i) & 0xFFFFFFFF;
		}
		for($kk = 0; $kk < $n; $kk++){
			$y = ($mt[$kk] & 0x80000000) | ($mt[($kk + 1) % $n] & 0x7fffffff);
			$mt[$kk] = $mt[($kk + $m) % $n] ^ ($y >> 1) ^ (($y & 1) !== 0 ? 0x9908b0df : 0);
		}
		$y = $mt[0];
		$y ^= $y >> 11;
		$y ^= ($y << 7) & 0x9d2c5680;
		$y ^= ($y << 15) & 0xefc60000;
		$y ^= $y >> 18;

		return (($y & 0xFFFFFFFF) % 10) === 0;
	}
}
