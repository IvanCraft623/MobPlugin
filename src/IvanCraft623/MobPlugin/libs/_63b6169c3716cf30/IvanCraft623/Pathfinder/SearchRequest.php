<?php

/*
 *  _____      _   _      __ _           _
 * |  __ \    | | | |    / _(_)         | |
 * | |__) |_ _| |_| |__ | |_ _ _ __   __| | ___ _ __
 * |  ___/ _` | __| '_ \|  _| | '_ \ / _` |/ _ \ '__|
 * | |  | (_| | |_| | | | | | | | | | (_| |  __/ |
 * |_|   \__,_|\__|_| |_|_| |_|_| |_|\__,_|\___|_|
 *
 * A PocketMine-MP virion that implements a mob-oriented pathfinding.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_63b6169c3716cf30\IvanCraft623\Pathfinder;

use pocketmine\math\Vector3;

final class SearchRequest{
	public readonly Vector3 $start;
	public readonly Vector3 $target;

	public function __construct(
		Vector3 $start,
		Vector3 $target,
		public readonly int $maxVisitedNodes,
		public readonly float $maxDistanceFromStart,
		public readonly int $reachRange = 1, //manhattan distance at which the target counts as reached
		public readonly bool $onGround = true, //whether the mob starts on the ground
		public readonly bool $startAtNode = false //start at the block of $start, a node of an earlier path, instead of where a mob there would stand
	){
		//A Position would drag its World along when sent to a worker
		$this->start = $start->asVector3();
		$this->target = $target->asVector3();
	}
}