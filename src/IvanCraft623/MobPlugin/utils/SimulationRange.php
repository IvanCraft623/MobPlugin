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

namespace IvanCraft623\MobPlugin\utils;

use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * What natural spawning and despawning share: which players they measure from, and how
 * far from them they reach when the world ticks few chunks.
 */
final class SimulationRange{
	private const LOW_TICK_RADIUS = 4;
	/** At a low chunk tick radius, vanilla spawns nothing farther than this from a player, and despawns what is. */
	public const LOW_TICK_RADIUS_MAX_PLAYER_DISTANCE = 44;

	private function __construct(){}

	public static function isLowTickRadius(World $world) : bool{
		return $world->getChunkTickRadius() <= self::LOW_TICK_RADIUS;
	}

	/**
	 * The positions natural spawning and despawning measure from. Spectators and dead
	 * players are left out.
	 *
	 * @return Position[]
	 * @phpstan-return list<Position>
	 */
	public static function getPlayerPositions(World $world) : array{
		$positions = [];
		foreach($world->getPlayers() as $player){
			if($player->canBeCollidedWith()){
				$positions[] = $player->getPosition();
			}
		}

		return $positions;
	}
}
