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
use pocketmine\entity\Living;
use pocketmine\math\Facing;

use pocketmine\math\Vector3;
use pocketmine\world\Position;

use function count;
use function max;

class BlockPattern {

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
	 * @param array<int, array<int, array<int, Closure(Block): bool>>>                    $pattern          The block pattern.
	 * @param Closure(Block $placedBlock): bool                                           $triggerCondition
	 * @param Closure(BlockPatternMatch $match, Block $placedBlock, ?Living $owner): void $onMatch
	 */
	public function __construct(
		private array $pattern,
		private Closure $triggerCondition,
		private Closure $onMatch
	) {
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
	 * @return array<int, array<int, array<int, Closure(Block): bool>>> The block pattern.
	 */
	public function getPattern() : array {
		return $this->pattern;
	}

	/**
	 * Matches a block pattern at a specific position and orientation.
	 *
	 * @param Position  $position         The position to match the pattern.
	 * @param int       $forwards         The forwards direction of the match.
	 * @param int       $up               The up direction of the match.
	 * @param ?Position $ignoreTriggerPos If set, skips validation for the block at this position (the trigger block).
	 *
	 * @return ?BlockPatternMatch Returns the block pattern match if successful, otherwise null.
	 */
	private function matches(Position $position, int $forwards, int $up, ?Position $ignoreTriggerPos = null) : ?BlockPatternMatch {
		$world = $position->getWorld();
		for ($widthIndex = 0; $widthIndex < $this->width; ++$widthIndex) {
			for ($heightIndex = 0; $heightIndex < $this->height; ++$heightIndex) {
				for ($depthIndex = 0; $depthIndex < $this->depth; ++$depthIndex) {
					$blockPos = $this->translateAndRotate($position, $forwards, $up, $widthIndex, $heightIndex, $depthIndex);
					if ($ignoreTriggerPos !== null && $blockPos->equals($ignoreTriggerPos)) {
						continue;
					}
					if (!$this->pattern[$depthIndex][$heightIndex][$widthIndex]($world->getBlock($blockPos))) {
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
	 * @param Position $position      The position to start searching from.
	 * @param bool     $ignoreTrigger If true, skips validation for the block at $position, assuming it
	 *                                is the trigger block that has just been placed and already satisfies
	 *                                the pattern condition.
	 *
	 * @return BlockPatternMatch|null The block pattern match, or null if no match is found.
	 */
	public function find(Position $position, bool $ignoreTrigger = false) : ?BlockPatternMatch {
		$maxDimension = max($this->width, $this->height, $this->depth);
		$ignoreTriggerPos = $ignoreTrigger ? $position : null;

		for ($x = $position->getX(); $x < $position->getX() + $maxDimension - 1; $x++) {
			for ($y = $position->getY(); $y < $position->getY() + $maxDimension - 1; $y++) {
				for ($z = $position->getZ(); $z < $position->getZ() + $maxDimension - 1; $z++) {
					foreach (Facing::ALL as $forwards) {
						foreach (Facing::ALL as $up) {
							if (Facing::axis($forwards) !== Facing::axis($up)) {
								$match = $this->matches(new Position($x, $y, $z, $position->getWorld()), $forwards, $up, $ignoreTriggerPos);
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
	 * Evaluate whether the newly placed block triggers the verification of this pattern.
	 */
	public function isTrigger(Block $placedBlock) : bool {
		return ($this->triggerCondition)($placedBlock);
	}

	/**
	 * Execute the action when the pattern matches in the world.
	 */
	public function execute(BlockPatternMatch $match, Block $placedBlock, ?Living $owner = null) : void {
		($this->onMatch)($match, $placedBlock, $owner);
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