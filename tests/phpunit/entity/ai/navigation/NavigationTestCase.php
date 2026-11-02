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

namespace IvanCraft623\MobPlugin\entity\ai\navigation;

use IvanCraft623\Pathfinder\evaluator\WalkNodeEvaluator;
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\Node;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathResult;
use IvanCraft623\Pathfinder\world\ArrayChunkSource;
use IvanCraft623\Pathfinder\world\Terrain;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;

abstract class NavigationTestCase extends TestCase{
	/**
	 * Nodes along x at y=64, z=0; the target is the last one.
	 */
	protected static function path(int $fromX, int $toX, PathResult $result = PathResult::REACHED) : Path{
		$nodes = [];
		for ($x = $fromX; $x <= $toX; ++$x) {
			$nodes[] = new Node($x, 64, 0);
		}
		return new Path($nodes, new Vector3($toX, 64, 0), $result);
	}

	protected static function searchSetup(int $maxVisitedNodes = 100, float $followRange = 16) : SearchSetup{
		return new SearchSetup(
			new WalkNodeEvaluator(),
			new MovementProfile(0.6, 1.95),
			new Terrain(new ArrayChunkSource(), 0, 256),
			new Vector3(0.5, 64, 0.5),
			true,
			$followRange,
			$maxVisitedNodes
		);
	}
}
