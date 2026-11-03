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

namespace IvanCraft623\MobPlugin\libs\_d231130ad4236385\IvanCraft623\Pathfinder;

use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function array_merge;
use function array_slice;
use function array_splice;
use function count;
use function max;
use function min;
use const INF;

class Path{

	//A corridor column packs, from the lowest bits: min y, max y, last node index, first node index
	private const CORRIDOR_Y_BITS = 12;
	private const CORRIDOR_Y_MASK = (1 << self::CORRIDOR_Y_BITS) - 1;
	private const CORRIDOR_Y_OFFSET = 1 << (self::CORRIDOR_Y_BITS - 1);
	private const CORRIDOR_INDEX_BITS = 19;
	private const CORRIDOR_INDEX_MASK = (1 << self::CORRIDOR_INDEX_BITS) - 1;
	private const CORRIDOR_MAX_Y_SHIFT = self::CORRIDOR_Y_BITS;
	private const CORRIDOR_LAST_SHIFT = 2 * self::CORRIDOR_Y_BITS;
	private const CORRIDOR_FIRST_SHIFT = self::CORRIDOR_LAST_SHIFT + self::CORRIDOR_INDEX_BITS;

	/** @var Node[] */
	private array $nodes;

	private int $nodeCount;

	private int $nextNodeIndex = 0;

	private Vector3 $target;

	private float $distToTarget;

	private PathResult $result;

	private int $nodeWidth;

	private int $nodeHeight;

	/**
	 * Blocks the search looked at for the nodes, per column. Null once the nodes changed, until needed again.
	 *
	 * @var int[]|null World::chunkHash() of the column => min y, max y and indexes of the last and first node using it, packed
	 * @phpstan-var array<int, int>|null
	 */
	private ?array $corridor = null;

	/**
	 * @var true[] World::chunkHash() of the chunk => true
	 * @phpstan-var array<int, true>
	 */
	private array $corridorChunks = [];

	/**
	 * @param Node[] $nodes
	 * @param int    $nodeWidth  blocks a node spans on the x and z axes
	 * @param int    $nodeHeight blocks a node spans on the y axis
	 */
	public function __construct(array $nodes, Vector3 $target, PathResult $result, int $nodeWidth = 1, int $nodeHeight = 1){
		if (count($nodes) > self::CORRIDOR_INDEX_MASK) {
			throw new \InvalidArgumentException("A path can't have more than " . self::CORRIDOR_INDEX_MASK . " nodes");
		}
		$this->nodes = $nodes;
		$this->nodeCount = count($nodes);
		$this->target = $target;
		$this->distToTarget = $this->nodeCount === 0 ? INF : $nodes[$this->nodeCount - 1]->distanceManhattan($target);
		$this->result = $result;
		$this->nodeWidth = $nodeWidth;
		$this->nodeHeight = $nodeHeight;
		$this->buildCorridor();
	}

	private function buildCorridor() : void{
		$corridor = [];
		$chunks = [];

		$previousY = null;
		foreach ($this->nodes as $index => $node) {
			$nodeY = $node->y();
			$previousY ??= $nodeY;
			$minY = min($nodeY, $previousY) - 1;
			$maxY = max($nodeY, $previousY) + $this->nodeHeight;
			$minX = $node->x() - 1;
			$maxX = $node->x() + $this->nodeWidth;
			$minZ = $node->z() - 1;
			$maxZ = $node->z() + $this->nodeWidth;
			for ($x = $minX; $x <= $maxX; $x++) {
				for ($z = $minZ; $z <= $maxZ; $z++) {
					$hash = World::chunkHash($x, $z);
					$column = $corridor[$hash] ?? null;
					$first = $index;
					$columnMinY = $minY;
					$columnMaxY = $maxY;
					if ($column !== null) {
						$first = ($column >> self::CORRIDOR_FIRST_SHIFT) & self::CORRIDOR_INDEX_MASK;
						$columnMinY = min($minY, ($column & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET);
						$columnMaxY = max($maxY, (($column >> self::CORRIDOR_MAX_Y_SHIFT) & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET);
					}
					$corridor[$hash] = ($first << self::CORRIDOR_FIRST_SHIFT) |
						($index << self::CORRIDOR_LAST_SHIFT) |
						(($columnMaxY + self::CORRIDOR_Y_OFFSET) << self::CORRIDOR_MAX_Y_SHIFT) |
						($columnMinY + self::CORRIDOR_Y_OFFSET);
				}
			}
			//A node spans less than a chunk, its corners are in every chunk it touches
			$minChunkX = $minX >> Chunk::COORD_BIT_SIZE;
			$maxChunkX = $maxX >> Chunk::COORD_BIT_SIZE;
			$minChunkZ = $minZ >> Chunk::COORD_BIT_SIZE;
			$maxChunkZ = $maxZ >> Chunk::COORD_BIT_SIZE;
			$chunks[World::chunkHash($minChunkX, $minChunkZ)] = true;
			$chunks[World::chunkHash($minChunkX, $maxChunkZ)] = true;
			$chunks[World::chunkHash($maxChunkX, $minChunkZ)] = true;
			$chunks[World::chunkHash($maxChunkX, $maxChunkZ)] = true;
			$previousY = $nodeY;
		}

		$this->corridor = $corridor;
		$this->corridorChunks = $chunks;
	}

	/**
	 * Whether the block is one the search looked at for a node still to be walked: the blocks the entity
	 * occupies there, the floor, the ring around them, and the column joining it to the previous node.
	 */
	public function isInCorridor(int $x, int $y, int $z) : bool{
		return $this->getCorridorColumn($x, $y, $z) !== null;
	}

	/**
	 * The nodes still to be walked that the block at x, y, z matters to, see {@link isInCorridor()}. A column the
	 * path crosses twice reports both crossings and everything between, so the invalidation may be wider than it is.
	 */
	public function findInvalidation(int $x, int $y, int $z) : ?PathInvalidation{
		$column = $this->getCorridorColumn($x, $y, $z);

		return $column === null ? null : new PathInvalidation(
			($column >> self::CORRIDOR_FIRST_SHIFT) & self::CORRIDOR_INDEX_MASK,
			($column >> self::CORRIDOR_LAST_SHIFT) & self::CORRIDOR_INDEX_MASK
		);
	}

	private function getCorridorColumn(int $x, int $y, int $z) : ?int{
		if ($this->corridor === null) {
			$this->buildCorridor();
		}
		$column = $this->corridor[World::chunkHash($x, $z)] ?? null;

		if ($column === null ||
			$y < ($column & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET ||
			$y > (($column >> self::CORRIDOR_MAX_Y_SHIFT) & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET ||
			(($column >> self::CORRIDOR_LAST_SHIFT) & self::CORRIDOR_INDEX_MASK) < $this->nextNodeIndex - 1
		) {
			return null;
		}

		return $column;
	}

	/**
	 * How to patch the path around the invalidation, or null if it reaches the node the mob just left (it may be
	 * standing on it) and the path has to be searched again from the mob.
	 */
	public function planRepair(PathInvalidation $invalidation) : ?PathRepair{
		$from = $invalidation->first - 1;
		if ($from < 0 || $from < $this->nextNodeIndex - 1 || $invalidation->last >= $this->nodeCount) {
			return null;
		}
		$rejoin = $invalidation->last + 1;

		return new PathRepair($from, $rejoin < $this->nodeCount ? $rejoin : null);
	}

	/**
	 * Chunks holding the blocks the search looked at for the nodes.
	 *
	 * @return true[] World::chunkHash() of the chunk => true
	 * @phpstan-return array<int, true>
	 */
	public function getCorridorChunks() : array{
		if ($this->corridor === null) {
			$this->buildCorridor();
		}

		return $this->corridorChunks;
	}

	public function advance() : void{
		++$this->nextNodeIndex;
	}

	public function notStarted() : bool{
		return $this->nextNodeIndex <= 0;
	}

	public function isDone() : bool{
		return $this->nextNodeIndex >= $this->nodeCount;
	}

	public function getEndNode() : ?Node{
		return $this->nodeCount !== 0 ? $this->nodes[$this->nodeCount - 1] : null;
	}

	public function getNode(int $index) : Node{
		return $this->nodes[$index];
	}

	public function truncateNodes(int $length) : void{
		if($this->nodeCount > $length){
			array_splice($this->nodes, $length);
			$this->nodeCount = $length;
			$this->corridor = null;
		}
	}

	public function replaceNode(int $index, Node $node) : void{
		$this->nodes[$index] = $node;
		$this->corridor = null;
	}

	/**
	 * @return Node[]
	 */
	public function getNodes() : array{
		return $this->nodes;
	}

	public function getNodeCount() : int{
		return $this->nodeCount;
	}

	public function getNextNodeIndex() : int{
		return $this->nextNodeIndex;
	}

	public function setNextNodeIndex(int $index) : void{
		$this->nextNodeIndex = $index;
	}

	/**
	 * Where the mob stands at the node: the center of the blocks it spans, at the node's y.
	 */
	public function getNodeCenter(int $index) : Vector3{
		$node = $this->nodes[$index];
		$offset = $this->nodeWidth * 0.5;
		return new Vector3($node->x + $offset, $node->y, $node->z + $offset);
	}

	public function getNodePos(int $index) : Vector3{
		return $this->nodes[$index]->asVector3();
	}

	public function getNextNodeCenter() : Vector3{
		return $this->getNodeCenter($this->nextNodeIndex);
	}

	public function getNextNode() : Node{
		return $this->nodes[$this->nextNodeIndex];
	}

	public function getNextNodePos() : Vector3{
		return $this->getNextNode()->asVector3();
	}

	public function getPreviousNode() : ?Node{
		return $this->nodes[$this->nextNodeIndex - 1] ?? null;
	}

	/**
	 * Index of the node still to walk closest to $position.
	 *
	 * @throws \LogicException if the path is done
	 */
	public function getClosestNodeIndex(Vector3 $position) : int{
		if ($this->isDone()) {
			throw new \LogicException("There is no node left to walk");
		}

		$closestIndex = $this->nextNodeIndex;
		$closestDistance = INF;
		for ($index = $this->nextNodeIndex; $index < $this->nodeCount; ++$index) {
			$distance = $this->nodes[$index]->distanceSquared($position);
			if ($distance < $closestDistance) {
				$closestIndex = $index;
				$closestDistance = $distance;
			}
		}
		return $closestIndex;
	}

	/**
	 * This path up to the node at $index, then $continuation, which starts at that node. The progress made along
	 * this path is kept, so $index can't be behind the node last walked.
	 *
	 * With $rejoinIndex the continuation also ends at that node and the rest of this path is kept after it,
	 * with its target and result: a detour around the nodes in between.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function splice(int $index, Path $continuation, ?int $rejoinIndex = null) : Path{
		$error = $this->getSpliceError($index, $continuation, $rejoinIndex);
		if ($error !== null) {
			throw new \InvalidArgumentException($error);
		}

		$nodes = array_slice($this->nodes, 0, $index + 1);
		if ($rejoinIndex === null) {
			$target = $continuation->target;
			$result = $continuation->result;
			$tail = array_slice($continuation->nodes, 1);
		} else {
			$target = $this->target;
			$result = $this->result;
			$tail = array_merge(array_slice($continuation->nodes, 1, $continuation->nodeCount - 2), array_slice($this->nodes, $rejoinIndex));
		}

		$spliced = new Path(array_merge($nodes, $tail), $target, $result, $this->nodeWidth, $this->nodeHeight);
		$spliced->nextNodeIndex = $this->nextNodeIndex;
		return $spliced;
	}

	/**
	 * Whether {@link splice()} accepts these arguments, for a path that may have changed since the continuation
	 * was searched.
	 */
	public function canSplice(int $index, Path $continuation, ?int $rejoinIndex = null) : bool{
		return $this->getSpliceError($index, $continuation, $rejoinIndex) === null;
	}

	private function getSpliceError(int $index, Path $continuation, ?int $rejoinIndex) : ?string{
		if ($index < $this->nextNodeIndex - 1 || $index >= $this->nodeCount) {
			return "Can't splice at node $index of $this->nodeCount, node $this->nextNodeIndex is next";
		}
		if ($continuation->nodeCount === 0 || !$continuation->nodes[0]->equals($this->nodes[$index])) {
			return "The continuation does not start at node $index";
		}
		if ($rejoinIndex === null) {
			return null;
		}
		if ($rejoinIndex <= $index || $rejoinIndex >= $this->nodeCount) {
			return "Can't rejoin at node $rejoinIndex after splicing at $index of $this->nodeCount";
		}
		if (!$continuation->nodes[$continuation->nodeCount - 1]->equals($this->nodes[$rejoinIndex])) {
			return "The continuation does not end at node $rejoinIndex";
		}
		return null;
	}

	public function equals(Path $other) : bool{
		if ($this->nodeCount !== $other->nodeCount) {
			return false;
		}
		foreach ($this->nodes as $index => $node) {
			if (!$node->equals($other->getNode($index))) {
				return false;
			}
		}
		return true;
	}

	public function getPathResult() : PathResult{
		return $this->result;
	}

	public function canReach() : bool{
		return $this->result === PathResult::REACHED;
	}

	public function getTarget() : Vector3{
		return clone $this->target;
	}

	public function getDistanceToTarget() : float{
		return $this->distToTarget;
	}
}