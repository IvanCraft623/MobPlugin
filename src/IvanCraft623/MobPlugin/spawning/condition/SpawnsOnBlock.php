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

namespace IvanCraft623\MobPlugin\spawning\condition;

use pocketmine\block\Block;

final class SpawnsOnBlock implements SpawnCondition{

	/** @phpstan-var array<int, true> item state id => true */
	private readonly array $itemStateIds;

	/**
	 * Blocks match by what makes them a distinct block (dirt type, colour), not by
	 * placement state like facing or snow layers.
	 *
	 * @phpstan-param Block[] $blocks  checked against the block under the feet
	 * @phpstan-param bool    $prevent whether the blocks forbid the spawn instead of allowing it
	 */
	public function __construct(
		array $blocks,
		private readonly bool $prevent
	){
		$itemStateIds = [];
		foreach($blocks as $block){
			$itemStateIds[$block->asItem()->getStateId()] = true;
		}
		$this->itemStateIds = $itemStateIds;
	}

	public function test(SpawnConditionContext $ctx) : bool{
		return isset($this->itemStateIds[$ctx->getBelowItemStateId()]) !== $this->prevent;
	}
}
