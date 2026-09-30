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

namespace IvanCraft623\MobPlugin\spawning\spawner;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;

final class SpyCondition implements SpawnCondition{
	public int $calls = 0;

	public int $keyContextCalls = 0;

	public function __construct(
		private readonly bool $cacheable,
		private readonly bool $readsBiomeOnly = false,
		private readonly bool $throws = false
	){}

	public function isCacheable() : bool{
		return $this->cacheable;
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$this->calls++;
		if($ctx instanceof KeyContext){
			$this->keyContextCalls++;
		}
		if($this->throws){
			throw new \RuntimeException("broken condition");
		}

		return $this->readsBiomeOnly ? $ctx->getBiomeId() >= 0 : $ctx->getY() >= -64;
	}
}
