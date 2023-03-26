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

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use function array_splice;
use function count;
use const INF;

class Path{

	/** @var Node[] */
	private array $nodes;

	private int $nextNodeIndex = 0;

	private Vector3 $target;

	private float $distToTarget;

	private bool $reached;

	/**
	 * @param Node[] $nodes
	 */
	public function __construct(array $nodes, Vector3 $target, bool $reached){
		$this->nodes = $nodes;
		$this->target = $target;
		$this->distToTarget = count($nodes) === 0 ? INF : $nodes[count($nodes) - 1]->distanceManhattan($target);
		$this->reached = $reached;
	}

	public function advance() : void{
		++$this->nextNodeIndex;
	}

	public function notStarted() : bool{
		return $this->nextNodeIndex <= 0;
	}

	public function isDone() : bool{
		return $this->nextNodeIndex >= count($this->nodes);
	}

	public function getEndNode() : ?Node{
		return count($this->nodes) !== 0 ? $this->nodes[count($this->nodes) - 1] : null;
	}

	public function getNode(int $index) : Node{
		return $this->nodes[$index];
	}

	public function truncateNodes(int $length) : void{
		if(count($this->nodes) > $length){
			array_splice($this->nodes, $length);
		}
	}

	public function replaceNode(int $index, Node $node) : void{
		$this->nodes[$index] = $node;
	}

	public function getNodeCount() : int{
		return count($this->nodes);
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

	public function getNextEntityPos(Entity $entity) : Vector3{
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
		if (count($this->nodes) !== count($other->nodes)) {
			return false;
		}
		foreach ($this->nodes as $index => $node) {
			if (!$node->equals($other->getNode($index))) {
				return false;
			}
		}
		return true;
	}

	public function canReach() : bool{
		return $this->reached;
	}

	public function getTarget() : Vector3{
		return clone $this->target;
	}

	public function getDistanceToTarget() : float{
		return $this->distToTarget;
	}
}
