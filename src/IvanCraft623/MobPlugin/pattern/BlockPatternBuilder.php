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
use function count;
use function implode;
use function str_split;
use function strlen;

/**
 * Constructor for a BlockPattern object
 */
class BlockPatternBuilder {
	/** @var array The pattern of the block. */
	private array $pattern = [];

	/**
	 * The lookup table to match characters with predicates
	 *
	 * @var Closure[] char => predicate
	 * @phpstan-var array<string, ?Closure(Block) : bool>
	 */
	private array $lookup = [];

	private int $height;
	private int $width;

	/**
	 * Initializes the lookup table with a default predicate for an empty space.
	 */
	private function __construct() {
		$this->lookup[" "] = fn(Block $block) : bool => true;
	}

	/**
	 * Adds an aisle to the block pattern.
	 *
	 * @param array $blocks The blocks forming the aisle.
	 * @phpstan-param non-empty-list<string> $blocks
	 *
	 * @return $this
	 */
	public function aisle(array $blocks) : self {
		$blocksCount = count($blocks);
		if ($blocks[0] === "") {
			throw new \InvalidArgumentException("Empty pattern for aisle");
		}

		if (count($this->pattern) === 0) {
			$this->height = $blocksCount;
			$this->width = strlen($blocks[0]);
		}

		if ($blocksCount !== $this->height) {
			throw new \InvalidArgumentException(
				"Expected aisle with height of " . $this->height . ", but was given one with a height of " . $blocksCount . ")"
			);
		}

		foreach ($blocks as $blockRow) {
			if (strlen($blockRow) !== $this->width) {
				throw new \InvalidArgumentException(
					"Not all rows in the given aisle are the correct width (expected " . $this->width . ", found one with " . strlen($blockRow) . ")"
				);
			}

			foreach (str_split($blockRow) as $char) {
				if (!isset($this->lookup[$char])) {
					$this->lookup[$char] = null;
				}
			}
		}

		$this->pattern[] = $blocks;

		return $this;
	}

	/**
	 * Starts building a block pattern.
	 *
	 * @return BlockPatternBuilder Returns a new instance of BlockPatternBuilder.
	 */
	public static function start() : BlockPatternBuilder {
		return new self();
	}

	/**
	 * Specifies a predicate for a character in the block pattern.
	 *
	 * @param string                $char      The character in the block pattern.
	 * @param Closure(Block) : bool $predicate The predicate function.
	 *
	 * @return $this
	 */
	public function where(string $char, Closure $predicate) : BlockPatternBuilder {
		$this->lookup[$char] = $predicate;

		return $this;
	}

	/**
	 * Builds the block pattern.
	 *
	 * @return BlockPattern Returns the built block pattern.
	 */
	public function build() : BlockPattern {
		return new BlockPattern($this->createPattern());
	}

	/**
	 * Creates the block pattern array using the lookup table.
	 *
	 * @return array Returns the block pattern array.
	 */
	private function createPattern() : array {
		$this->ensureAllCharactersMatched();
		$blockPattern = [];

		foreach ($this->pattern as $depthIndex => $blocks) {
			foreach ($blocks as $heightIndex => $blockRow) {
				foreach (str_split($blockRow) as $widthIndex => $char) {
					$blockPattern[$depthIndex][$heightIndex][$widthIndex] = $this->lookup[$char];
				}
			}
		}

		return $blockPattern;
	}

	/**
	 * Ensures all characters in the block pattern have associated predicates.
	 *
	 * @throws \RuntimeException If any character in the block pattern lacks a predicate.
	 */
	private function ensureAllCharactersMatched() : void {
		$missingPredicates = [];

		foreach ($this->lookup as $char => $predicate) {
			if ($predicate === null) {
				$missingPredicates[] = $char;
			}
		}

		if (count($missingPredicates) !== 0) {
			throw new \RuntimeException("Predicates for character(s) " . implode(", ", $missingPredicates) . " are missing");
		}
	}
}
