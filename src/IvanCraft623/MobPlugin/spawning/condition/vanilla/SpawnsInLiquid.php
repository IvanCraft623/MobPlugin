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
use IvanCraft623\MobPlugin\spawning\plan\LiquidConstrained;
use pocketmine\block\BlockTypeIds;

/**
 * The vanilla spawns_underwater / spawns_lava components: the spawn position's feet
 * block must be the given liquid.
 */
final class SpawnsInLiquid implements SpawnCondition, LiquidConstrained{
	public function __construct(
		private readonly int $liquidTypeId
	){
		if($liquidTypeId !== BlockTypeIds::WATER && $liquidTypeId !== BlockTypeIds::LAVA){
			throw new \InvalidArgumentException("SpawnsInLiquid requires BlockTypeIds::WATER or BlockTypeIds::LAVA");
		}
	}

	public function getRequiredLiquidTypeId() : int{
		return $this->liquidTypeId;
	}

	public function getEvaluationCost() : int{
		return 2; // one cached block-type lookup
	}

	public function test(SpawnConditionContext $ctx) : bool{
		return $ctx->env->getBlockTypeId() === $this->liquidTypeId;
	}
}
