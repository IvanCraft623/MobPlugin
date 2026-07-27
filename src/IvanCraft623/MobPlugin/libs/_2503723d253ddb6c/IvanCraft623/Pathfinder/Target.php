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

namespace IvanCraft623\MobPlugin\libs\_2503723d253ddb6c\IvanCraft623\Pathfinder;

use const INF;

class Target extends Node {

	protected float $bestHeuristic = INF;

	protected Node $bestNode;

	private bool $reached = false;

	/**
	 * @return Target
	 */
	public static function fromObject(Node $node){
		return new Target($node->x(), $node->y(), $node->z());
	}

	public function updateBest(float $heuristic, Node $node) : void{
		if ($heuristic < $this->bestHeuristic) {
			$this->bestHeuristic = $heuristic;
			$this->bestNode = $node;
		}
	}

	public function getBestNode() : Node{
		return $this->bestNode;
	}

	public function setReached(bool $reached = true) : void{
		$this->reached = $reached;
	}

	public function reached() : bool{
		return $this->reached;
	}
}