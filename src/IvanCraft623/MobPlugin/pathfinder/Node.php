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
use pocketmine\world\World;
use function abs;
use function sqrt;
use const PHP_INT_MIN;

/**
 * @phpstan-import-type BlockPosHash from World
 */
class Node extends Vector3 {

	/**
	 * @phpstan-var BlockPosHash
	 */
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
		parent::__construct($x, $y, $z);

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

	/**
	 * @phpstan-return BlockPosHash
	 */
	public static function createHash(int $x, int $y, int $z) : int {
		return World::blockHash($x, $y, $z);
	}

	/**
	 * @phpstan-return int
	 */
	public function getX() : float|int{
		return parent::getX();
	}

	/**
	 * @phpstan-return int
	 */
	public function getY() : float|int{
		return parent::getY();
	}

	/**
	 * @phpstan-return int
	 */
	public function getZ() : float|int{
		return parent::getZ();
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
