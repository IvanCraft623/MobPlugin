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

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\pathfinder\evaluator\NodeEvaluator;
use pocketmine\math\Vector3;
use pocketmine\world\World;

use function array_map;
use function array_reduce;
use function array_reverse;
use function count;
use const INF;

class PathFinder {

	public const FUDGING = 1.5;

	private int $maxVisitedNodes;

	private NodeEvaluator $nodeEvaluator;

	private BinaryHeap $openSet;

	public function __construct(NodeEvaluator $nodeEvaluator, int $maxVisitedNodes) {
		$this->nodeEvaluator = $nodeEvaluator;
		$this->maxVisitedNodes = $maxVisitedNodes;
		$this->openSet = new BinaryHeap();
	}

	public function getNodeEvaluator() : NodeEvaluator{
		return $this->nodeEvaluator;
	}

	/**
	 * Attempts to find a path from the mob position to one of the specified targets.
	 *
	 * @param Vector3[] $targets              Targets to pathfind to.
	 * @param float     $maxDistanceFromStart Maximum distance at which to search for a path.
	 * @param int       $reachRange           Distance which the entity can interact with a node.
	 * @param float     $maxDistanceFromStart Limiting factor of the nodes amount that can be visited.
	 *
	 * @return ?Path Resulting path, or null if no path could be found.
	 */
	public function findPath(World $world, Mob $mob, array $targets, float $maxDistanceFromStart, int $reachRange, float $maxVisitedNodesPercentage) : ?Path {
		$this->openSet->clear();
		$this->nodeEvaluator->prepare($world, $mob);
		$startNode = $this->nodeEvaluator->getStart();

		/** @var Target[] $actuallyTargets */
		$actuallyTargets = [];
		foreach ($targets as $pos) {
			$actuallyTargets[] = $this->nodeEvaluator->getGoal($pos->x, $pos->y, $pos->z);
		}

		$result = $this->findPathRecursive($startNode, $actuallyTargets, $maxDistanceFromStart, $reachRange, $maxVisitedNodesPercentage);
		$this->nodeEvaluator->done();
		return $result;
	}

	/**
	 * Attempts to find a path from the start node to one of the specified targets.
	 *
	 * @param Target[] $targets
	 */
	private function findPathRecursive(Node $startNode, array $targets, float $maxDistanceFromStart, int $reachRange, float $maxVisitedNodesPercentage) : ?Path {
		$startNode->g = 0.0;
		$startNode->h = $this->getBestH($startNode, $targets);
		$startNode->f = $startNode->h;

		$this->openSet->clear();
		$this->openSet->insert($startNode);

		$visitedNodes = 0;
		$maxVisitedNodes = (int) ($this->maxVisitedNodes * $maxVisitedNodesPercentage);

		/** @var Target[] $reachableTargets */
		$reachableTargets = [];

		$maxDistanceFromStartSqr = $maxDistanceFromStart ** 2;

		while (!$this->openSet->isEmpty()) {
			if (++$visitedNodes >= $maxVisitedNodes) {
				break;
			}

			$current = $this->openSet->pop();
			$current->closed = true;

			foreach ($targets as $target) {
				if ($current->distanceManhattan($target) <= $reachRange) {
					$target->setReached();
					$reachableTargets[] = $target;
				}
			}

			if (count($reachableTargets) !== 0) {
				break;
			}

			if ($current->distanceSquared($startNode) < $maxDistanceFromStartSqr) {
				foreach ($this->nodeEvaluator->getNeighbors($current) as $neighbor) {
					$distance = $this->distance($current, $neighbor);
					$neighbor->walkedDistance = $current->walkedDistance + $distance;
					$newNeighborG = $current->g + $distance + $neighbor->costMalus;

					if ($neighbor->walkedDistance < $maxDistanceFromStart && (!$neighbor->inOpenSet() || $newNeighborG < $neighbor->g)) {
						$neighbor->cameFrom = $current;
						$neighbor->g = $newNeighborG;
						$neighbor->h = $this->getBestH($neighbor, $targets) * self::FUDGING;

						if ($neighbor->inOpenSet()) {
							$this->openSet->changeCost($neighbor, $neighbor->g + $neighbor->h);
						} else {
							$neighbor->f = $neighbor->g + $neighbor->h;
							$this->openSet->insert($neighbor);
						}
					}
				}
			}
		}

		$path = null;

		if (count($reachableTargets) !== 0) {
			$path = array_reduce(array_map(function(Target $target) : Path{
				return $this->reconstructPath($target->getBestNode(), $target->asVector3(), true);
			}, $reachableTargets), function(?Path $carry, Path $current) : Path {
				return ($carry !== null && $carry->getNodeCount() < $current->getNodeCount()) ? $carry : $current;
			}, null);
		} else {
			$path = array_reduce(array_map(function(Target $target) : Path{
				return $this->reconstructPath($target->getBestNode(), $target->asVector3(), false);
			}, $targets), function(?Path $carry, Path $current) : Path {
				if ($carry === null) {
					return $current;
				}
				$distanceCarry = $carry->getDistanceToTarget();
				$distanceCurrent = $current->getDistanceToTarget();
				if ($distanceCarry === $distanceCurrent) {
					return $carry->getNodeCount() < $current->getNodeCount() ? $carry : $current;
				}
				return $distanceCarry < $distanceCurrent ? $carry : $current;
			}, null);
		}

		return $path;
	}

	public function distance(Node $node1, Node $node2) : float{
		return $node1->distance($node2);
	}

	/**
	 * @param Target[] $targets
	 */
	public function getBestH(Node $node, array $targets) : float{
		$bestH = INF;
		foreach ($targets as $target) {
			$h = $node->distance($target);
			$target->updateBest($h, $node);

			if ($h < $bestH) {
				$bestH = $h;
			}
		}

		return $bestH;
	}

	private function reconstructPath(Node $startNode, Vector3 $target, bool $reached) : Path{
		/** @var Node[] $nodes */
		$nodes = [];
		$currentNode = $startNode;
		$nodes[] = $currentNode;

		while (($from = $currentNode->cameFrom) !== null) {
			/** @var Node $from */
			$currentNode = $from;
			$nodes[] = $from;
		}

		return new Path(array_reverse($nodes), $target, $reached);
	}
}
