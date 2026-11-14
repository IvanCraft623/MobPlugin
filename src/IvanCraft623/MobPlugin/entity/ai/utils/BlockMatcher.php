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

namespace IvanCraft623\MobPlugin\entity\ai\utils;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function array_keys;

/**
 * A block type plus an extra condition on its state.
 *
 * @template TBlock of Block
 */
final class BlockMatcher {

	/**
	 * @param int                   $typeId  {@see BlockTypeIds}
	 * @param \Closure(TBlock):bool $isValid extra condition, takes the class of blocks of that type
	 */
	public function __construct(
		private int $typeId,
		private \Closure $isValid
	) {
	}

	public function getTypeId() : int{
		return $this->typeId;
	}

	public function matches(Block $block) : bool{
		if ($block->getTypeId() !== $this->typeId) {
			return false;
		}

		//Blocks of the type are of TBlock
		/** @var \Closure(Block):bool $isValid */
		$isValid = $this->isValid;

		return $isValid($block);
	}

	/**
	 * Yields the blocks a matcher accepts in the box around the origin, nearest first. Take {@see \Generator::current()}
	 * for the nearest. For a single type, {@see NearestBlockFinder::find()} is cheaper.
	 *
	 * @param BlockMatcher<*> ...$matchers
	 *
	 * @phpstan-return \Generator<int, Block, void, void>
	 */
	public static function find(World $world, Vector3 $origin, int $horizontalRange, int $verticalRange, BlockMatcher ...$matchers) : \Generator{
		/** @var array<int, list<BlockMatcher<*>>> $matchersByType */
		$matchersByType = [];
		foreach ($matchers as $matcher) {
			$matchersByType[$matcher->typeId][] = $matcher;
		}

		foreach (NearestBlockFinder::find($world, $origin, $horizontalRange, $verticalRange, ...array_keys($matchersByType)) as $position) {
			$block = $world->getBlock($position);
			foreach ($matchersByType[$block->getTypeId()] as $matcher) {
				if ($matcher->matches($block)) {
					yield $block;

					break;
				}
			}
		}
	}
}
