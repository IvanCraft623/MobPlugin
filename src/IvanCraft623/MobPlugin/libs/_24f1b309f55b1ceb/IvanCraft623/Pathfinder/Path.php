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

namespace IvanCraft623\MobPlugin\libs\_24f1b309f55b1ceb\IvanCraft623\Pathfinder;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function array_splice;
use function count;
use function max;
use function min;
use const INF;

class Path{

	private const CORRIDOR_Y_BITS = 12;
	private const CORRIDOR_Y_MASK = (1 << self::CORRIDOR_Y_BITS) - 1;
	private const CORRIDOR_Y_OFFSET = 1 << (self::CORRIDOR_Y_BITS - 1);

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
	 * @var int[]|null World::chunkHash() of the column => min y, max y and index of the last node using it, packed
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
					$columnMinY = $column === null ? $minY : min($minY, ($column & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET);
					$columnMaxY = $column === null ? $maxY : max($maxY, (($column >> self::CORRIDOR_Y_BITS) & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET);
					$corridor[$hash] = ($index << (2 * self::CORRIDOR_Y_BITS)) |
						(($columnMaxY + self::CORRIDOR_Y_OFFSET) << self::CORRIDOR_Y_BITS) |
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
		if ($this->corridor === null) {
			$this->buildCorridor();
		}
		$column = $this->corridor[World::chunkHash($x, $z)] ?? null;

		return $column !== null &&
			$y >= ($column & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET &&
			$y <= (($column >> self::CORRIDOR_Y_BITS) & self::CORRIDOR_Y_MASK) - self::CORRIDOR_Y_OFFSET &&
			($column >> (2 * self::CORRIDOR_Y_BITS)) >= $this->nextNodeIndex - 1;
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

	public function getEntityPosAtNode(Entity $entity, int $index) : Vector3{
		$node = $this->nodes[$index];
		$x = $node->getX() + (int) ($entity->getSize()->getWidth() + 1.0) * 0.5;
		$y = $node->getY();
		$z = $node->getZ() + (int) ($entity->getSize()->getWidth() + 1.0) * 0.5;
		return new Vector3($x, $y, $z);
	}

	public function getNodePos(int $index) : Vector3{
		return $this->nodes[$index]->asVector3();
	}

	public function getNextEntityPosition(Entity $entity) : Vector3{
		return $this->getEntityPosAtNode($entity, $this->nextNodeIndex);
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