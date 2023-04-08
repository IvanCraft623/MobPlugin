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

namespace IvanCraft623\MobPlugin\pathfinder;

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
