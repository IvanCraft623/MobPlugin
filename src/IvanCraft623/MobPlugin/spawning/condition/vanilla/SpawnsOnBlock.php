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

namespace IvanCraft623\MobPlugin\spawning\condition\vanilla;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;

/**
 * spawns_on_block_filter / _prevented_filter: resolved block type id sets; the checked
 * block is the one under the spawn position. Unresolvable names are dropped at parse.
 */
final class SpawnsOnBlock implements SpawnCondition{
	/**
	 * @phpstan-param array<int, true>|null $typeIds resolved block type ids, null when
	 *     the component is absent; an empty set never matches (fail closed).
	 * @phpstan-throws \InvalidArgumentException when both sets are absent
	 */
	public function __construct(
		private readonly ?array $typeIds,
		private readonly bool $prevent
	){
		if($this->typeIds === null){
			throw new \InvalidArgumentException("SpawnsOnBlock requires a block set");
		}
	}

	public function getEvaluationCost() : int{
		return 2; // one cached below-block lookup + set contains
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$blockUnder = $ctx->env->getBelowBlockTypeId();
		$contained = isset($this->typeIds[$blockUnder]);

		return $this->prevent ? !$contained : $contained;
	}
}
