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

namespace IvanCraft623\MobPlugin\libs\_cfb343694bb4760c\IvanCraft623\Pathfinder;

use IvanCraft623\MobPlugin\libs\_cfb343694bb4760c\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_cfb343694bb4760c\IvanCraft623\Pathfinder\task\AsyncPathFinderTask;
use IvanCraft623\MobPlugin\libs\_cfb343694bb4760c\IvanCraft623\Pathfinder\world\SyncBlockGetter;

use pmmp\thread\ThreadSafeArray;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\Position;
use pocketmine\world\World;

use function array_reverse;
use function floor;
use function igbinary_serialize;
use function max;
use function min;
use const INF;

class PathFinder {

	public const FUDGING = 1.5;

	/**
	 * Attempts to find a path from the position to one of the specified targets.
	 *
	 * @param World   $world                The world.
	 * @param Vector3 $position             Start position.
	 * @param Vector3 $target               Target to pathfind to.
	 * @param int     $maxVisitedNodes      Limiting the nodes amount that can be visited.
	 * @param float   $maxDistanceFromStart Maximum distance at which to search for a path.
	 * @param int     $reachRange           Distance which the entity can interact with a node.
	 *
	 * @return Path Resulting path, or null if no path could be found.
	 */
	public static function findPath(NodeEvaluator $evaluator, World $world, Vector3 $position, Vector3 $target, int $maxVisitedNodes, float $maxDistanceFromStart, int $reachRange = 1) : Path {
		$evaluator->prepare(new SyncBlockGetter($world), $position);
		$startNode = $evaluator->getStart();

		$actualTarget = $evaluator->getGoal(
			(int) floor($target->getX()),
			(int) floor($target->getY()),
			(int) floor($target->getZ())
		);
		$result = static::actuallyFindPath($evaluator, $startNode, $actualTarget, $maxVisitedNodes, $maxDistanceFromStart, $reachRange);

		$evaluator->done();

		return $result;
	}

	public static function findPathAsync(\Closure $onCompletion, NodeEvaluator $evaluator, World $world, Vector3 $position, Vector3 $target, int $maxVisitedNodes, float $maxDistanceFromStart, int $reachRange = 1) : AsyncPathfinderTask {
		//Serialize all chunks between start and end
		$serializedChunks = [];

		$minX = min($position->getFloorX() >> Chunk::COORD_BIT_SIZE, $target->getFloorX() >> Chunk::COORD_BIT_SIZE);
		$maxX = max($position->getFloorX() >> Chunk::COORD_BIT_SIZE, $target->getFloorX() >> Chunk::COORD_BIT_SIZE);
		$minZ = min($position->getFloorZ() >> Chunk::COORD_BIT_SIZE, $target->getFloorZ() >> Chunk::COORD_BIT_SIZE);
		$maxZ = max($position->getFloorZ() >> Chunk::COORD_BIT_SIZE, $target->getFloorZ() >> Chunk::COORD_BIT_SIZE);

		for($x = $minX; $x <= $maxX; $x++) {
			for($z = $minZ; $z <= $maxZ; $z++) {
				$chunk = $world->getChunk($x, $z);
				if ($chunk !== null) {
					$serializedChunks[World::chunkHash($x, $z)] = FastChunkSerializer::serializeTerrain($chunk);
				}
			}
		}

		//Submit async task
		Server::getInstance()->getAsyncPool()->submitTask($task = new AsyncPathfinderTask(
			nodeEvaluator: igbinary_serialize($evaluator) ?? throw new \RuntimeException("Failed to serealize evaluator")
			,
			start: igbinary_serialize($position->asVector3()) ?? throw new \RuntimeException("Failed to serealize start"),
			target: igbinary_serialize($target->asVector3()) ?? throw new \RuntimeException("Failed to serealize target"),
			worldId: $world->getId(),
			maxVisitedNodes: $maxVisitedNodes,
			maxDistanceFromStart: $maxDistanceFromStart,
			reachRange: $reachRange,
			defaultChunks: ThreadSafeArray::fromArray($serializedChunks),
			worldMinY: $world->getMinY(),
			worldMaxY: $world->getMaxY(),
			onCompletion: $onCompletion
		));

		return $task;
	}

	/**
	 * Attempts to find a path from the start node to the target.
	 */
	public static function actuallyFindPath(
		NodeEvaluator $evaluator,
		Node $startNode,
		Target $target,
		int $maxVisitedNodes,
		float $maxDistanceFromStart,
		int $reachRange
	) : Path {
		$openSet = new BinaryHeap();

		$startNode->g = 0.0;
		$startNode->h = static::getBestH($startNode, [$target]);
		$startNode->f = $startNode->h;

		$openSet->insert($startNode);

		$visitedNodes = 0;

		$maxDistanceFromStartSqr = $maxDistanceFromStart ** 2;

		while (!$openSet->isEmpty()) {
			if (++$visitedNodes >= $maxVisitedNodes) {
				break;
			}

			$current = $openSet->pop();
			$current->closed = true;

			if ($current->distanceManhattan($target) <= $reachRange) {
				$target->setReached();

				break;
			}

			if ($current->distanceSquared($startNode) < $maxDistanceFromStartSqr) {
				foreach ($evaluator->getNeighbors($current) as $neighbor) {
					$distance = $current->distance($neighbor);
					$neighbor->walkedDistance = $current->walkedDistance + $distance;
					$newNeighborG = $current->g + $distance + $neighbor->costMalus;

					if ($neighbor->walkedDistance < $maxDistanceFromStart && (!$neighbor->inOpenSet() || $newNeighborG < $neighbor->g)) {
						$neighbor->cameFrom = $current;
						$neighbor->g = $newNeighborG;
						$neighbor->h = static::getBestH($neighbor, [$target]) * self::FUDGING;

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

		return self::reconstructPath($target->getBestNode(), $target->asVector3(), $target->reached());
	}

	/**
	 * @param Target[] $targets
	 */
	public static function getBestH(Node $node, array $targets) : float{
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

	private static function reconstructPath(Node $startNode, Vector3 $target, bool $reached) : Path{
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