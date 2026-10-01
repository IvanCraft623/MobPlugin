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

use IvanCraft623\MobPlugin\spawning\SpawnBand;

final class RangeCondition implements SpawnCondition{
	private const KIND_BRIGHTNESS = 0;
	private const KIND_DIFFICULTY = 1;
	private const KIND_HEIGHT = 2;
	private const KIND_DISTANCE = 3;
	private const KIND_WORLD_AGE = 4;
	private const KIND_BAND = 5;

	private function __construct(
		private readonly int $kind,
		private readonly ?float $min,
		private readonly ?float $max
	){
		if($min !== null && $max !== null && $min > $max){
			throw new \InvalidArgumentException("Range minimum ($min) must not exceed maximum ($max)");
		}
	}

	public static function brightness(int $min, int $max) : self{
		return new self(self::KIND_BRIGHTNESS, $min, $max);
	}

	public static function difficulty(int $min, int $max) : self{
		return new self(self::KIND_DIFFICULTY, $min, $max);
	}

	public static function height(?int $min, ?int $max) : self{
		return new self(self::KIND_HEIGHT, $min, $max);
	}

	public static function distance(?float $min, ?float $max) : self{
		return new self(self::KIND_DISTANCE, $min, $max);
	}

	public static function worldAge(?int $min, ?int $max) : self{
		return new self(self::KIND_WORLD_AGE, $min, $max);
	}

	public static function band(SpawnBand $band) : self{
		return new self(self::KIND_BAND, $band->value, $band->value);
	}

	public function isCacheable() : bool{
		return true;
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$value = match($this->kind){
			self::KIND_BRIGHTNESS => $ctx->getLight(),
			self::KIND_DIFFICULTY => $ctx->getDifficulty(),
			self::KIND_HEIGHT => $ctx->getY(),
			self::KIND_DISTANCE => $ctx->getNearestPlayerDistance(),
			self::KIND_WORLD_AGE => $ctx->getTime(),
			self::KIND_BAND => $ctx->getBand()->value,
			default => throw new \LogicException("Unknown range kind $this->kind"),
		};

		return ($this->min === null || $value >= $this->min) && ($this->max === null || $value <= $this->max);
	}
}
