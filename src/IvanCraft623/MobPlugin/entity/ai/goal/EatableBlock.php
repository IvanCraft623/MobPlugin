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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use Closure;
use IvanCraft623\MobPlugin\entity\ai\utils\BlockMatcher;
use pocketmine\block\Block;

/**
 * A kind of block that can be eaten from, and the block it becomes once eaten from.
 *
 * @template TBlock of Block
 */
final class EatableBlock{

	/** @var BlockMatcher<TBlock> */
	private BlockMatcher $matcher;

	/**
	 * @param int                           $typeId    {@see BlockTypeIds}
	 * @param Closure(TBlock) : Block       $eaten     the block it becomes once eaten from
	 * @param (Closure(TBlock) : bool)|null $isEatable whether the block has something to eat, null if it always has
	 */
	public function __construct(
		int $typeId,
		private Closure $eaten,
		?Closure $isEatable = null
	){
		$this->matcher = new BlockMatcher($typeId, $isEatable ?? static fn() : bool => true);
	}

	/**
	 * @return BlockMatcher<TBlock>
	 */
	public function getMatcher() : BlockMatcher{
		return $this->matcher;
	}

	/**
	 * @return Block|null what the block becomes once eaten from, null if it can't be eaten from
	 */
	public function getEaten(Block $block) : ?Block{
		if (!$this->matcher->matches($block)) {
			return null;
		}

		//Blocks of the type are of TBlock
		/** @var Closure(Block) : Block $eaten */
		$eaten = $this->eaten;

		return $eaten($block);
	}
}
