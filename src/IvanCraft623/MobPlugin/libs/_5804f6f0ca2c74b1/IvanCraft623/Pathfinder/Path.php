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

namespace IvanCraft623\MobPlugin\libs\_5804f6f0ca2c74b1\IvanCraft623\Pathfinder;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use function array_splice;
use function count;
use const INF;

class Path{

	/** @var Node[] */
	private array $nodes;

	private int $nodeCount;

	private int $nextNodeIndex = 0;

	private Vector3 $target;

	private float $distToTarget;

	private PathResult $result;

	/**
	 * @param Node[] $nodes
	 */
	public function __construct(array $nodes, Vector3 $target, PathResult $result){
		$this->nodes = $nodes;
		$this->nodeCount = count($nodes);
		$this->target = $target;
		$this->distToTarget = $this->nodeCount === 0 ? INF : $nodes[$this->nodeCount - 1]->distanceManhattan($target);
		$this->result = $result;
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
		}
	}

	public function replaceNode(int $index, Node $node) : void{
		$this->nodes[$index] = $node;
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