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

final class SlimeChunkConditionTest extends TestCase{

	/**
	 * Every expected value comes from slime-finder-pe:
	 * https://github.com/depressed-pho/slime-finder-pe
	 *
	 * @return \Generator<string, array{int, int, bool}>
	 */
	public static function chunks() : \Generator{
		yield "origin" => [0, 0, false];
		yield "next to the origin" => [-1, 0, true];
		yield "positive" => [109, 3, true];
		yield "positive, not slimy" => [110, 3, false];
		yield "next along Z" => [3, 1, true];
		yield "next along Z, not slimy" => [3, 2, false];
		yield "negative" => [-13, -16, true];
		yield "mixed signs" => [-8, 15, true];
		yield "far" => [-1874998, 1875002, true];
		yield "far, not slimy" => [123456, -654321, false];
		yield "32-bit extremes" => [2147483647, -2147483648, true];
		yield "32-bit extremes, not slimy" => [-2147483648, 2147483647, false];
	}

	/**
	 * @dataProvider chunks
	 */
	public function testMatchesReferenceImplementation(int $chunkX, int $chunkZ, bool $slimy) : void{
		self::assertSame($slimy, SlimeChunkCondition::isSlimeChunk($chunkX, $chunkZ));
	}

	/**
	 * The last chunk's answer is kept: asking for the same chunk twice, or for another
	 * one in between, must never return a stale answer.
	 */
	public function testRememberedChunkIsNeverStale() : void{
		foreach(self::chunks() as $first => [$firstX, $firstZ, $firstSlimy]){
			foreach(self::chunks() as $second => [$secondX, $secondZ, $secondSlimy]){
				$where = "$first, then $second";
				self::assertSame($firstSlimy, SlimeChunkCondition::isSlimeChunk($firstX, $firstZ), $where);
				self::assertSame($firstSlimy, SlimeChunkCondition::isSlimeChunk($firstX, $firstZ), $where);
				self::assertSame($secondSlimy, SlimeChunkCondition::isSlimeChunk($secondX, $secondZ), $where);
				self::assertSame($firstSlimy, SlimeChunkCondition::isSlimeChunk($firstX, $firstZ), $where);
			}
		}
	}
}
