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

use IvanCraft623\MobPlugin\entity\PathfinderMob;

/**
 * Looks for a block to walk to, but only once every few seconds.
 */
abstract class MoveToBlockGoal extends Goal{

	private int $searchCooldown = 0; //ticks until the next search

	/**
	 * @param int $searchInterval ticks between searches, plus up to as many random ones
	 */
	public function __construct(
		protected PathfinderMob $entity,
		private int $searchInterval = 200
	) {
		$this->setFlags(Goal::FLAG_MOVE);
	}

	/**
	 * Whether it is in a state to look for a block.
	 */
	abstract protected function canSearch() : bool;

	/**
	 * Looks for the block to walk to, and keeps it for start().
	 */
	abstract protected function findBlock() : bool;

	final public function canUse() : bool{
		if (!$this->canSearch()) {
			return false;
		}
		if ($this->searchCooldown > 0) {
			--$this->searchCooldown;

			return false;
		}
		$this->searchCooldown = $this->adjustedTickDelay(
			$this->searchInterval + $this->entity->getRandom()->nextBoundedInt($this->searchInterval)
		);

		return $this->findBlock();
	}
}
