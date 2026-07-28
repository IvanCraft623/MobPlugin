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

namespace IvanCraft623\MobPlugin\libs\_7bad8f044eb00826\IvanCraft623\Pathfinder;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use function abs;

/**
 * @phpstan-import-type BlockPosHash from World
 */
class Node extends Vector3 {

	/** @phpstan-var BlockPosHash */
	private int $hash;

	public int $heapIdx = -1;

	public float $g;
	public float $h;
	public float $f;

	public ?Node $cameFrom = null;

	public bool $closed = false;

	public float $walkedDistance = 0;

	public float $costMalus = 0;

	public BlockPathType $type;

	public function __construct(int $x, int $y, int $z) {
		parent::__construct($x, $y, $z);

		$this->hash = self::createHash($x, $y, $z);
		$this->type = BlockPathType::BLOCKED;
	}

	public function cloneAndMove(int $x, int $y, int $z) : Node{
		$newNode = clone $this;
		$newNode->x = $x;
		$newNode->y = $y;
		$newNode->z = $z;
		$newNode->hash = self::createHash($x, $y, $z);

		return $newNode;
	}

	/**
	 * @phpstan-return BlockPosHash
	 */
	public static function createHash(int $x, int $y, int $z) : int {
		return World::blockHash($x, $y, $z);
	}

	public function x() : int{
		return (int) $this->x;
	}

	public function y() : int{
		return (int) $this->y;
	}

	public function z() : int{
		return (int) $this->z;
	}

	public function hashCode() : int{
		return $this->hash;
	}

	public function inOpenSet() : bool{
		return $this->heapIdx >= 0;
	}

	public function distanceManhattan(Vector3 $target) : float {
		return abs($target->x - $this->x) + abs($target->y - $this->y) + abs($target->z - $this->z);
	}
}