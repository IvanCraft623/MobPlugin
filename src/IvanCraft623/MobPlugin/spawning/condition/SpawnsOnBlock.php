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

final class SpawnsOnBlock implements SpawnCondition{
	/**
	 * @phpstan-param array<int, true> $typeIds block type ids checked against the block under the feet
	 */
	public function __construct(
		private readonly array $typeIds,
		private readonly bool $prevent
	){}

	public function isCacheable() : bool{
		return true;
	}

	public function test(SpawnConditionContext $ctx) : bool{
		return isset($this->typeIds[$ctx->getBelowTypeId()]) !== $this->prevent;
	}
}
