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

use IvanCraft623\MobPlugin\spawning\parse\SpawnRuleGroupBuilder;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\spawner\RegionPopulation;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use PHPUnit\Framework\TestCase;

final class SpawnConditionTest extends TestCase{

	public function testRangeBoundsAreInclusiveAndNullIsOpen() : void{
		$height = RangeCondition::height(10, 20);
		self::assertFalse($height->test(new StubContext(y: 9)));
		self::assertTrue($height->test(new StubContext(y: 10)));
		self::assertTrue($height->test(new StubContext(y: 20)));
		self::assertFalse($height->test(new StubContext(y: 21)));

		$below = RangeCondition::height(null, 40);
		self::assertTrue($below->test(new StubContext(y: -64)));
		self::assertFalse($below->test(new StubContext(y: 41)));

		$distance = RangeCondition::distance(24.0, null);
		self::assertFalse($distance->test(new StubContext(nearestPlayerDistance: 23.9)));
		self::assertTrue($distance->test(new StubContext(nearestPlayerDistance: 1000.0)));
	}

	public function testInvertedRangeIsRejected() : void{
		$this->expectException(\InvalidArgumentException::class);
		RangeCondition::difficulty(3, 1);
	}

	public function testBrightnessSubtractsWeatherOnlyWhenAsked() : void{
		$ctx = new StubContext(light: 9, weatherLightPenalty: 3);
		self::assertFalse(RangeCondition::brightness(0, 7)->test($ctx));
		self::assertTrue(RangeCondition::brightness(0, 7, true)->test($ctx));
	}

	public function testBandAndLiquidMatchExactly() : void{
		self::assertTrue(RangeCondition::band(SpawnBand::CAVE)->test(new StubContext(band: SpawnBand::CAVE)));
		self::assertFalse(RangeCondition::band(SpawnBand::CAVE)->test(new StubContext(band: SpawnBand::SURFACE)));
		self::assertTrue(RangeCondition::liquid(SpawnLiquid::WATER)->test(new StubContext(feetLiquid: SpawnLiquid::WATER)));
		self::assertFalse(RangeCondition::liquid(SpawnLiquid::WATER)->test(new StubContext(feetLiquid: SpawnLiquid::LAVA)));
	}

	public function testDensityLimitReadsTheBandCount() : void{
		$condition = new DensityLimitCondition("minecraft:cow", 2, null);
		$population = new RegionPopulation([], [SpawnBand::SURFACE->value => ["minecraft:cow" => 2]]);
		self::assertFalse($condition->test(new StubContext(population: $population)));
		self::assertTrue($condition->test(new StubContext(band: SpawnBand::CAVE, population: $population)), "no cave limit");
		self::assertTrue($condition->test(new StubContext()));
	}

	public function testCombinatorsAreCacheableOnlyWhenAllChildrenAre() : void{
		$pure = RangeCondition::height(0, 10);
		$impure = new class implements SpawnCondition{
			public function isCacheable() : bool{
				return false;
			}

			public function test(SpawnConditionContext $ctx) : bool{
				return true;
			}
		};

		self::assertTrue((new AllOf([$pure, new Not($pure)]))->isCacheable());
		self::assertFalse((new AllOf([$pure, $impure]))->isCacheable());
		self::assertFalse((new AnyOf([$pure, $impure]))->isCacheable());
		self::assertFalse((new Not($impure))->isCacheable());
		self::assertFalse((new AnyOf([new AllOf([$impure])]))->isCacheable());
	}

	public function testNeverSpawningGroupIsDropped() : void{
		$builder = new SpawnRuleGroupBuilder("minecraft:test");
		$builder->addCondition(RangeCondition::height(0, 10));
		$builder->markNeverSpawns();
		self::assertNull($builder->build());
	}

	public function testSingleHabitatMarkerPinsTheBand() : void{
		$builder = new SpawnRuleGroupBuilder("minecraft:test");
		$builder->allowHabitatBand(SpawnBand::CAVE);
		$group = $builder->build();
		self::assertNotNull($group);
		self::assertFalse($group->matches(new StubContext(band: SpawnBand::SURFACE)));

		$builder->allowHabitatBand(SpawnBand::SURFACE);
		$group = $builder->build();
		self::assertNotNull($group);
		self::assertSame([], $group->getConditions());
	}

	public function testGroupDerivesRequiredLiquid() : void{
		self::assertSame(SpawnLiquid::LAVA, (new SpawnRuleGroup([RangeCondition::height(0, 10), RangeCondition::liquid(SpawnLiquid::LAVA)]))->getRequiredLiquid());
		self::assertSame(SpawnLiquid::NONE, (new SpawnRuleGroup([RangeCondition::height(0, 10)]))->getRequiredLiquid());
	}
}
