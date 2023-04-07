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

use pocketmine\math\Vector3;
use function abs;
use function sqrt;
use const PHP_INT_MIN;

class Node {

	public int $x;
	public int $y;
	public int $z;

	private int $hash;

	public int $heapIdx = -1;

	public float $g;
	public float $h;
	public float $f;

	public ?Node $cameFrom = null;

	public bool $closed = false;

	public float $walkedDistance = 0;

	public float $costMalus = 0;

	public BlockPathTypes $type;

	public function __construct(int $x, int $y, int $z) {
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
		$this->hash = self::createHash($x, $y, $z);
		$this->type = BlockPathTypes::BLOCKED();
	}

	public function cloneAndMove(int $x, int $y, int $z) : Node{
		$newNode = clone $this;
		$newNode->x = $x;
		$newNode->y = $y;
		$newNode->z = $z;
		$newNode->hash = self::createHash($x, $y, $z);

		return $newNode;
	}

	public static function createHash(int $x, int $y, int $z) : int {
		return $y & 0xFF | ($x & 32767) << 8 | ($z & 32767) << 24 | ($x < 0 ? PHP_INT_MIN : 0) | ($z < 0 ? 32768 : 0);
	}

	public function getX() : int{
		return $this->x;
	}

	public function getY() : int{
		return $this->y;
	}

	public function getZ() : int{
		return $this->z;
	}

	public function distanceTo(Node $node) : float {
		return sqrt(($node->x - $this->x) ** 2 + ($node->y - $this->y) ** 2 + ($node->z - $this->z) ** 2);
	}

	public function distanceToXZ(Node $node) : float {
		return sqrt(($node->x - $this->x) ** 2 + ($node->z - $this->z) ** 2);
	}

	public function distanceToPos(Vector3 $pos) : float {
		return sqrt(($pos->x - $this->x) ** 2 + ($pos->y - $this->y) ** 2 + ($pos->z - $this->z) ** 2);
	}

	public function distanceToSqr(Node|Vector3 $target) : float {
		return ($target->x - $this->x) ** 2 + ($target->y - $this->y) ** 2 + ($target->z - $this->z) ** 2;
	}

	public function distanceManhattan(Node|Vector3 $target) : float {
		return abs($target->x - $this->x) + abs($target->y - $this->y) + abs($target->z - $this->z);
	}

	public function asVector3() : Vector3{
		return new Vector3($this->x, $this->y, $this->z);
	}

	public function equals(Node $other) : bool{
		return $this->hash === $other->hash;
	}

	public function hashCode() : int{
		return $this->hash;
	}

	public function inOpenSet() : bool{
		return $this->heapIdx >= 0;
	}
}
