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

use function array_slice;

class BinaryHeap {

	/** @var Node[] */
	private array $heap = [];

	private int $size = 0;

	public function insert(Node $node) : Node {
		if ($node->heapIdx >= 0) {
			throw new \InvalidArgumentException("Invalid node");
		} else {
			$this->heap[$this->size] = $node;
			$node->heapIdx = $this->size;
			$this->upHeap($this->size++);
			return $node;
		}
	}

	public function clear() : void {
		$this->size = 0;
	}

	public function peek() : Node {
		return $this->heap[0];
	}

	public function pop() : Node {
		$topNode = $this->heap[0];
		$this->heap[0] = $this->heap[--$this->size];
		unset($this->heap[$this->size]);
		if ($this->size > 0) {
			$this->downHeap(0);
		}

		$topNode->heapIdx = -1;
		return $topNode;
	}

	public function remove(Node $node) : void {
		$this->heap[$node->heapIdx] = $this->heap[--$this->size];
		unset($this->heap[$this->size]);
		if ($this->size > $node->heapIdx) {
			if ($this->heap[$node->heapIdx]->f < $node->f) {
				$this->upHeap($node->heapIdx);
			} else {
				$this->downHeap($node->heapIdx);
			}
		}

		$node->heapIdx = -1;
	}

	public function changeCost(Node $node, int $newCost) : void {
		$previousCost = $node->f;
		$node->f = $newCost;
		if ($newCost < $previousCost) {
			$this->upHeap($node->heapIdx);
		} else {
			$this->downHeap($node->heapIdx);
		}
	}

	public function size() : int {
		return $this->size;
	}

	private function upHeap(int $index) : void{
		$node = $this->heap[$index];

		while ($index > 0) {
			$parentIndex = ($index - 1) >> 1;
			$parent = $this->heap[$parentIndex];
			if (!($node->f > $parent->f)) {
				break;
			}

			$this->heap[$index] = $parent;
			$parent->heapIdx = $index;
			$index = $parentIndex;
		}

		$this->heap[$index] = $node;
		$node->heapIdx = $index;
	}

	private function downHeap(int $index) : void {
		$node = $this->heap[$index];
		$currentNodeCost = $node->f;

		while (true) {
			$left = 2 * $index + 1;
			$right = $left + 1;
			if ($left >= $this->size) {
				break;
			}

			$minChild = ($right >= $this->size || $this->heap[$left]->f < $this->heap[$right]->f) ? $left : $right;
			if ($this->heap[$minChild]->f >= $currentNodeCost) {
				break;
			}

			$this->heap[$index] = $this->heap[$minChild];
			$this->heap[$index]->heapIdx = $index;
			$index = $minChild;
		}

		$this->heap[$index] = $node;
		$node->heapIdx = $index;
	}

	public function isEmpty() : bool{
		return $this->size === 0;
	}

	public function getHeap() : array {
		return array_slice($this->heap, 0, $this->size);
	}
}
