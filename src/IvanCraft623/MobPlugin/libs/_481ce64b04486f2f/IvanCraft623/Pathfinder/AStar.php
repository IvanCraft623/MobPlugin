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

namespace IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder;

use Closure;
use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\world\BlockGetter;
use function array_reverse;
use function floor;

/**
 * Searches paths for one mob shape over one terrain; not reentrant.
 */
final class AStar {

	public const FUDGING = 1.5;

	public function __construct(
		private readonly NodeEvaluator $evaluator,
		private readonly MovementProfile $profile,
		private readonly BlockGetter $blockGetter
	){
	}

	/**
	 * @phpstan-param (Closure() : bool)|null $isCancelled checked between nodes
	 */
	public function search(SearchRequest $request, ?Closure $isCancelled = null) : Path{
		$this->evaluator->prepare(new SearchContext($this->blockGetter, $this->profile, $request->start, $request->onGround));
		try{
			$target = $request->target;
			return $this->run(
				$request->startAtNode ? $this->evaluator->getStartNode($request->start) : $this->evaluator->getStart(),
				$this->evaluator->getGoal(floor($target->x), floor($target->y), floor($target->z)),
				$request,
				$isCancelled
			);
		}finally{
			$this->evaluator->done();
		}
	}

	/**
	 * @phpstan-param (Closure() : bool)|null $isCancelled
	 */
	private function run(Node $startNode, Target $target, SearchRequest $request, ?Closure $isCancelled) : Path{
		$openSet = new BinaryHeap();

		$startNode->g = 0.0;
		$startNode->h = $this->heuristic($startNode, $target);
		$startNode->f = $startNode->h;

		$openSet->insert($startNode);

		$visitedNodes = 0;
		$maxDistanceFromStart = $request->maxDistanceFromStart;
		$maxDistanceFromStartSqr = $maxDistanceFromStart ** 2;

		$result = PathResult::BLOCKED;

		while (!$openSet->isEmpty()) {
			if ($isCancelled !== null && $isCancelled()) {
				$result = PathResult::CANCELLED;
				break;
			}
			if (++$visitedNodes >= $request->maxVisitedNodes) {
				$result = PathResult::EXHAUSTED;

				break;
			}

			$current = $openSet->pop();
			$current->closed = true;

			if ($current->distanceManhattan($target) <= $request->reachRange) {
				$target->setReached();
				$result = PathResult::REACHED;

				break;
			}

			if ($current->distanceSquared($startNode) < $maxDistanceFromStartSqr) {
				foreach ($this->evaluator->getNeighbors($current) as $neighbor) {
					$distance = $current->distance($neighbor);
					$neighbor->walkedDistance = $current->walkedDistance + $distance;
					$newNeighborG = $current->g + $distance + $neighbor->costMalus;

					if ($neighbor->walkedDistance < $maxDistanceFromStart && (!$neighbor->inOpenSet() || $newNeighborG < $neighbor->g)) {
						$neighbor->cameFrom = $current;
						$neighbor->g = $newNeighborG;
						$neighbor->h = $this->heuristic($neighbor, $target) * self::FUDGING;

						if ($neighbor->inOpenSet()) {
							$openSet->changeCost($neighbor, $neighbor->g + $neighbor->h);
						} else {
							$neighbor->f = $neighbor->g + $neighbor->h;
							$openSet->insert($neighbor);
						}
					}
				}
			}
		}

		return $this->reconstructPath($target, $result);
	}

	private function heuristic(Node $node, Target $target) : float{
		$h = $node->distance($target);
		$target->updateBest($h, $node);

		return $h;
	}

	private function reconstructPath(Target $target, PathResult $result) : Path{
		$currentNode = $target->getBestNode();
		/** @var Node[] $nodes */
		$nodes = [$currentNode];

		while (($from = $currentNode->cameFrom) !== null) {
			$currentNode = $from;
			$nodes[] = $from;
		}

		return new Path(array_reverse($nodes), $target->asVector3(), $result, $this->profile->nodeWidth, $this->profile->nodeHeight);
	}
}