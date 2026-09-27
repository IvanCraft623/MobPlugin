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

/**
 * Habitat band: surface when the position lies above the highest block of its column,
 * cave otherwise.
 */
enum SpawnBand{
	case SURFACE;
	case CAVE;

	/**
	 * Resolves the band of a position from the Y of the highest non-air block of its
	 * column (the world minimum when the column is empty).
	 */
	public static function fromPosition(float $y, int $surfaceY) : self{
		return $y > $surfaceY ? self::SURFACE : self::CAVE;
	}
}
