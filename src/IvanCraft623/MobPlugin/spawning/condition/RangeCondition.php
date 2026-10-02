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

final class RangeCondition implements SpawnCondition{
	private const KIND_BRIGHTNESS = 0;
	private const KIND_BLOCK_LIGHT = 2;
	private const KIND_WORLD_AGE = 3;

	private function __construct(
		private readonly int $kind,
		private readonly ?int $min,
		private readonly ?int $max
	){
		if($min !== null && $max !== null && $min > $max){
			throw new \InvalidArgumentException("Range minimum ($min) must not exceed maximum ($max)");
		}
	}

	public static function brightness(int $min, int $max) : self{
		return new self(self::KIND_BRIGHTNESS, $min, $max);
	}

	/**
	 * Light from blocks alone, without the sky.
	 */
	public static function blockLight(int $min, int $max) : self{
		return new self(self::KIND_BLOCK_LIGHT, $min, $max);
	}

	public static function worldAge(?int $min, ?int $max) : self{
		return new self(self::KIND_WORLD_AGE, $min, $max);
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$value = match($this->kind){
			self::KIND_BRIGHTNESS => $ctx->getLight(),
			self::KIND_BLOCK_LIGHT => $ctx->getBlockLight(),
			self::KIND_WORLD_AGE => $ctx->getTime(),
			default => throw new \LogicException("Unknown range kind $this->kind"),
		};

		return ($this->min === null || $value >= $this->min) && ($this->max === null || $value <= $this->max);
	}
}
