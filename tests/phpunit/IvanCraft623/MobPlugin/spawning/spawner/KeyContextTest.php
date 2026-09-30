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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use PHPUnit\Framework\TestCase;
use function in_array;

final class KeyContextTest extends TestCase{
	/**
	 * Adding a key getter changes what a cache key means: update this list deliberately.
	 */
	private const KEY_GETTERS = ["getBiomeId", "getBand", "getDifficulty", "getFeetLiquid"];

	public function testKeyGettersReturnTheKeyAndEveryOtherGetterThrows() : void{
		$ctx = new KeyContext(7, SpawnBand::CAVE, 2, SpawnLiquid::WATER);
		$expected = ["getBiomeId" => 7, "getBand" => SpawnBand::CAVE, "getDifficulty" => 2, "getFeetLiquid" => SpawnLiquid::WATER];

		$methods = (new \ReflectionClass(SpawnConditionContext::class))->getMethods();
		self::assertNotEmpty($methods);
		foreach($methods as $method){
			$name = $method->getName();
			if(in_array($name, self::KEY_GETTERS, true)){
				self::assertSame($expected[$name], $ctx->$name(), $name);
				continue;
			}
			try{
				$ctx->$name();
				self::fail("KeyContext::$name() must throw PointInputRequired");
			}catch(PointInputRequired){
				self::addToAssertionCount(1);
			}
		}
	}

	public function testPointInputRequiredIsNotAnException() : void{
		self::assertNotInstanceOf(\Exception::class, PointInputRequired::get());
	}

	public function testFromCopiesOnlyTheKey() : void{
		$ctx = KeyContext::from(new StubContext(biomeId: 3, band: SpawnBand::CAVE, difficulty: 1, feetLiquid: SpawnLiquid::LAVA, y: 12));
		self::assertSame(3, $ctx->getBiomeId());
		self::assertSame(SpawnBand::CAVE, $ctx->getBand());
		self::assertSame(1, $ctx->getDifficulty());
		self::assertSame(SpawnLiquid::LAVA, $ctx->getFeetLiquid());
		$this->expectException(PointInputRequired::class);
		$ctx->getY();
	}
}
