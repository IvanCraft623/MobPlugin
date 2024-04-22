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

use Closure;
use pocketmine\block\Block;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;

use pocketmine\world\Position;
use function count;
use function max;

class BlockPattern {

	/** @var Closure[][][] The pattern of the block. */
	private array $pattern;

	/** @var int The depth of the block pattern. */
	private int $depth;

	/** @var int The height of the block pattern. */
	private int $height;

	/** @var int The width of the block pattern. */
	private int $width;

	/**
	 * BlockPattern constructor.
	 * Initializes the block pattern with the given pattern.
	 *
	 * @param Closure[][][] $pattern The block pattern.
	 */
	public function __construct(array $pattern) {
		$this->pattern = $pattern;
		$this->depth = count($pattern);

		if ($this->depth > 0) {
			$this->height = count($pattern[0]);
			if ($this->height > 0) {
				$this->width = count($pattern[0][0]);
			} else {
				$this->width = 0;
			}
		} else {
			$this->height = 0;
			$this->width = 0;
		}
	}

	/**
	 * Gets the depth of the block pattern.
	 *
	 * @return int The depth of the block pattern.
	 */
	public function getDepth() : int {
		return $this->depth;
	}

	/**
	 * Gets the height of the block pattern.
	 *
	 * @return int The height of the block pattern.
	 */
	public function getHeight() : int {
		return $this->height;
	}

	/**
	 * Gets the width of the block pattern.
	 *
	 * @return int The width of the block pattern.
	 */
	public function getWidth() : int {
		return $this->width;
	}

	/**
	 * Gets the block pattern.
	 * Visible for testing purposes.
	 *
	 * @return Closure[][][] The block pattern.
	 */
	public function getPattern() : array {
		return $this->pattern;
	}

	/**
	 * Matches a block pattern at a specific position and orientation.
	 *
	 * @param Position $position The position to match the pattern.
	 * @param int      $forwards The forwards direction of the match.
	 * @param int      $up       The up direction of the match.
	 *
	 * @return ?BlockPatternMatch Returns the block pattern match if successful, otherwise null.
	 */
	private function matches(Position $position, int $forwards, int $up) : ?BlockPatternMatch {
		$world = $position->getWorld();
		for ($widthIndex = 0; $widthIndex < $this->width; ++$widthIndex) {
			for ($heightIndex = 0; $heightIndex < $this->height; ++$heightIndex) {
				for ($depthIndex = 0; $depthIndex < $this->depth; ++$depthIndex) {
					if (!$this->pattern[$depthIndex][$heightIndex][$widthIndex]($world->getBlock($this->translateAndRotate($position, $forwards, $up, $widthIndex, $heightIndex, $depthIndex)))) {
						return null;
					}
				}
			}
		}

		return new BlockPatternMatch($position, $forwards, $up, $this->width, $this->height, $this->depth);
	}

	/**
	 * Finds the block pattern match in the world.
	 *
	 * @param Position $position The position to start searching from.
	 * @return BlockPatternMatch|null The block pattern match, or null if no match is found.
	 */
	public function find(Position $position) : ?BlockPatternMatch {
		$maxDimension = max($this->width, $this->height, $this->depth);

		for ($x = $position->getX(); $x < $position->getX() + $maxDimension - 1; $x++) {
			for ($y = $position->getY(); $y < $position->getY() + $maxDimension - 1; $y++) {
				for ($z = $position->getZ(); $z < $position->getZ() + $maxDimension - 1; $z++) {
					foreach (Facing::ALL as $forwards) {
						foreach (Facing::ALL as $up) {
							if (Facing::axis($forwards) !== Facing::axis($up)) {
								$match = $this->matches(new Position($x, $y, $z, $position->getWorld()), $forwards, $up);
								if ($match !== null) {
									return $match;
								}
							}
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Translates and rotates a position based on the given directions.
	 *
	 * @param Vector3 $position The position to translate and rotate.
	 * @param int     $forwards The forwards direction.
	 * @param int     $up       The up direction.
	 * @param int     $x        The x-coordinate offset.
	 * @param int     $y        The y-coordinate offset.
	 * @param int     $z        The z-coordinate offset.
	 *
	 * @return Vector3 The translated and rotated position.
	 */
	public static function translateAndRotate(Vector3 $position, int $forwards, int $up, int $x, int $y, int $z) : Vector3 {
		if (Facing::axis($forwards) === Facing::axis($up)) {
			throw new \InvalidArgumentException("Invalid forwards & up combination");
		}

		$forwardVector = Vector3::zero()->getSide($forwards);
		$upVector = Vector3::zero()->getSide($up);
		$rightVector = $forwardVector->cross($upVector);

		return $position
			->addVector($upVector->multiply(-$y))
			->addVector($rightVector->multiply($x))
			->addVector($forwardVector->multiply($z)
		);

	}
}
