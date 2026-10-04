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

/**
 * The lighter the position, the likelier it passes: never at light 0, always at the bound
 * or above. It passes when the light exceeds a random level below the bound.
 */
final class LightChanceCondition implements SpawnCondition{
	/**
	 * @param int $bound the light level from which it always passes
	 */
	public function __construct(
		private readonly int $bound
	){
		if($bound < 1){
			throw new \InvalidArgumentException("Bound must be at least 1, got $bound");
		}
	}

	public function test(SpawnConditionContext $ctx) : bool{
		return $ctx->getLight() > $ctx->getRandom()->nextBoundedInt($this->bound);
	}
}
