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

/**
 * A block type plus an extra condition on its state.
 */
final class BlockMatcher {

	/**
	 * @param int                  $typeId  {@see BlockTypeIds}
	 * @param \Closure(Block):bool $isValid extra condition
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
		return $block->getTypeId() === $this->typeId && ($this->isValid)($block);
	}
}
