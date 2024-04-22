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

namespace IvanCraft623\MobPlugin\pattern;

use pocketmine\block\Block;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class BlockPatternMatch {
	/** @var Vector3 The front top left position of the match. */
	private Vector3 $frontTopLeft;

	/** @var int The forwards direction of the match. */
	private int $forwards;

	/** @var int The up direction of the match. */
	private int $up;

	/** @var int The width of the match. */
	private int $width;

	/** @var int The height of the match. */
	private int $height;

	/** @var int The depth of the match. */
	private int $depth;

	/**
	 * BlockPatternMatch constructor.
	 *
	 * @param Vector3 $frontTopLeft The front top left position of the match.
	 * @param int     $forwards     The forwards direction of the match.
	 * @param int     $up           The up direction of the match.
	 * @param int     $width        The width of the match.
	 * @param int     $height       The height of the match.
	 * @param int     $depth        The depth of the match.
	 */
	public function __construct(Vector3 $frontTopLeft, int $forwards, int $up, int $width, int $height, int $depth) {
		$this->frontTopLeft = $frontTopLeft;
		$this->forwards = $forwards;
		$this->up = $up;
		$this->width = $width;
		$this->height = $height;
		$this->depth = $depth;
	}

	/**
	 * Gets the front top left position of the match.
	 *
	 * @return Vector3 The front top left position of the match.
	 */
	public function getFrontTopLeft() : Vector3 {
		return $this->frontTopLeft;
	}

	/**
	 * Gets the forwards direction of the match.
	 *
	 * @return int The forwards direction of the match.
	 */
	public function getForwards() : int {
		return $this->forwards;
	}

	/**
	 * Gets the up direction of the match.
	 *
	 * @return int The up direction of the match.
	 */
	public function getUp() : int {
		return $this->up;
	}

	/**
	 * Gets the width of the match.
	 *
	 * @return int The width of the match.
	 */
	public function getWidth() : int {
		return $this->width;
	}

	/**
	 * Gets the height of the match.
	 *
	 * @return int The height of the match.
	 */
	public function getHeight() : int {
		return $this->height;
	}

	/**
	 * Gets the depth of the match.
	 *
	 * @return int The depth of the match.
	 */
	public function getDepth() : int {
		return $this->depth;
	}

	/**
	 * Gets the block at the specified position within the match.
	 *
	 * @param int $x The x-coordinate offset within the match.
	 * @param int $y The y-coordinate offset within the match.
	 * @param int $z The z-coordinate offset within the match.
	 * @return Block The block at the specified position within the match.
	 */
	public function getBlock(int $x, int $y, int $z, World $world) : Block {
		return $world->getBlock(BlockPattern::translateAndRotate($this->frontTopLeft, $this->getForwards(), $this->getUp(), $x, $y, $z));
	}

	/**
	 * Returns a string representation of the block pattern match.
	 *
	 * @return string A string representation of the block pattern match.
	 */
	public function __toString() : string {
		return "BlockPatternMatch(up=" . Facing::toString($this->up) . ",forwards=" . Facing::toString($this->forwards) . ",frontTopLeft=" . $this->frontTopLeft . ")";
	}
}
