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

use function is_bool;

final class Not implements ReducibleCondition{
	public function __construct(
		private readonly SpawnCondition $condition
	){}

	public function test(SpawnConditionContext $ctx) : bool{
		return !$this->condition->test($ctx);
	}

	public function reduce(CacheableConditionContext $ctx) : SpawnCondition|bool{
		$reduced = CompositeCondition::reduceCondition($this->condition, $ctx);
		if(is_bool($reduced)){
			return !$reduced;
		}

		return $reduced === $this->condition ? $this : new self($reduced);
	}
}
