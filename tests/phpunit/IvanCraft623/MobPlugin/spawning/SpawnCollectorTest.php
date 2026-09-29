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

use pocketmine\utils\Random;
use PHPUnit\Framework\TestCase;
use function sqrt;

final class SpawnCollectorTest extends TestCase{

	/**
	 * Samples stay inside the spawn ring horizontally — never under or next to the player
	 * (the old spherical sample projected to XZ could land at distance 0).
	 */
	public function testRingOffsetStaysInsideTheRing() : void{
		$random = new Random(42);
		$inner = 0;
		for($i = 0; $i < 10000; $i++){
			[$dx, $dz] = SpawnCollector::ringOffset($random);
			$distance = sqrt($dx * $dx + $dz * $dz);
			self::assertGreaterThanOrEqual(SpawnCollector::MIN_PLAYER_DISTANCE - 1e-9, $distance);
			self::assertLessThanOrEqual(SpawnCollector::MAX_PLAYER_DISTANCE + 1e-9, $distance);
			if($distance < 34){
				$inner++;
			}
		}
		// Uniform over area: the 24-34 band holds (34² - 24²) / (44² - 24²) ≈ 42.5% of it.
		self::assertEqualsWithDelta(0.425, $inner / 10000, 0.02);
	}
}
