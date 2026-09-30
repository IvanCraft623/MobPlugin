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

namespace IvanCraft623\MobPlugin\spawning;

use IvanCraft623\MobPlugin\spawning\parse\SpawnData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\BrightnessFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\DensityLimitData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\DelayFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\SpawnAboveBlockFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\WeightData;
use IvanCraft623\MobPlugin\spawning\parse\BlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\ComponentParseContext;
use PHPUnit\Framework\TestCase;
use function json_encode;
use function is_string;

/**
 * Guards the generated XxxData payload models: maps concrete payload shapes (required,
 * array-typed) through JsonMapper via ComponentParseContext, so model/data drift fails here.
 */
final class SpawnModelCoverageTest extends TestCase{

	public function testBrightnessFilterModelMaps() : void{
		/** @var BrightnessFilterData $m */
		$m = self::context("brightness_filter", ["min" => 0, "max" => 7, "adjust_for_weather" => true])->map(BrightnessFilterData::class);
		self::assertSame(0, $m->min);
		self::assertSame(7, $m->max);
		self::assertTrue($m->adjust_for_weather);
	}

	public function testBrightnessFilterDefaultsWhenAbsent() : void{
		/** @var BrightnessFilterData $m */
		$m = self::context("brightness_filter", [])->map(BrightnessFilterData::class);
		self::assertNull($m->min);
		self::assertNull($m->max);
		self::assertNull($m->adjust_for_weather);
	}

	public function testDensityLimitModelMaps() : void{
		/** @var DensityLimitData $m */
		$m = self::context("density_limit", ["surface" => 8, "underground" => 16])->map(DensityLimitData::class);
		self::assertSame(8, $m->surface);
		self::assertSame(16, $m->underground);
	}

	public function testWeightRequiredDefaultIsEnforced() : void{
		/** @var WeightData $m */
		$m = self::context("weight", ["default" => 100])->map(WeightData::class);
		self::assertSame(100, $m->default);
	}

	public function testDelayFilterModelMaps() : void{
		/** @var DelayFilterData $m */
		$m = self::context("delay_filter", ["identifier" => "day", "min" => 5, "max" => 10, "spawn_chance" => 50])->map(DelayFilterData::class);
		self::assertSame("day", $m->identifier);
		self::assertSame(50, $m->spawn_chance);
	}

	public function testSpawnAboveBlockFilterArrayFieldMaps() : void{
		// spawns_above_block_filter.blocks is an array-typed field; absence leaves it null.
		/** @var SpawnAboveBlockFilterData $m */
		$m = self::context("spawns_above_block_filter", ["distance" => 3])->map(SpawnAboveBlockFilterData::class);
		self::assertSame(3, $m->distance);
		self::assertNull($m->blocks);
	}

	/**
	 * @phpstan-param array<string, mixed> $payload
	 */
	private static function context(string $component, array $payload) : ComponentParseContext{
		$encoded = json_encode([$component => $payload]);
		self::assertTrue(is_string($encoded), "test payload must encode to JSON");

		return new ComponentParseContext(SpawnData::fromJson((string) $encoded), $component, new BlockNameResolver(), new BiomeTagMap([]));
	}
}