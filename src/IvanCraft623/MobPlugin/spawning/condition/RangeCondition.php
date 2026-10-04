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
 * A value of the attempt within an inclusive range.
 */
abstract class RangeCondition implements SpawnCondition{
	/**
	 * @param int|null $min null for no lower bound
	 * @param int|null $max null for no upper bound
	 */
	protected function __construct(
		private readonly ?int $min,
		private readonly ?int $max
	){
		if($min !== null && $max !== null && $min > $max){
			throw new \InvalidArgumentException("Range minimum ($min) must not exceed maximum ($max)");
		}
	}

	abstract protected function read(SpawnConditionContext $ctx) : int;

	public function test(SpawnConditionContext $ctx) : bool{
		$value = $this->read($ctx);

		return ($this->min === null || $value >= $this->min) && ($this->max === null || $value <= $this->max);
	}
}