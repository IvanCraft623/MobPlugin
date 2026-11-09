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

use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\world\Terrain;
use pocketmine\math\Vector3;

/**
 * What a search from the mob runs with, read when it starts.
 *
 * @internal
 */
final class SearchSetup{
	public function __construct(
		public readonly NodeEvaluator $evaluator,
		public readonly MovementProfile $profile,
		public readonly Terrain $terrain,
		public readonly Vector3 $start,
		public readonly bool $onGround,
		public readonly float $followRange,
		public readonly int $maxVisitedNodes
	){
	}
}