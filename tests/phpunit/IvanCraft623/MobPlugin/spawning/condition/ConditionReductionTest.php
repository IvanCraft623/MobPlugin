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

use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\spawner\KeyContext;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use function is_array;
use function is_bool;

/**
 * Reducing a condition tree under a key never changes what it decides, on random trees.
 */
final class ConditionReductionTest extends TestCase{
	private const TREES = 600;
	private const CONTEXTS_PER_TREE = 50;
	private const MAX_DEPTH = 4;

	private const TAGS = ["cold", "wet", "high"];

	private static BiomeTagMap $tags;

	public static function setUpBeforeClass() : void{
		self::$tags = new BiomeTagMap([
			1 => ["cold" => true],
			2 => ["wet" => true, "high" => true],
			3 => ["cold" => true, "wet" => true],
			4 => [],
		]);
	}

	public function testReducedTreeDecidesTheSameOnEveryAttempt() : void{
		$random = new Random(31337);
		$decided = 0;
		$partial = 0;
		$untouched = 0;
		for($tree = 0; $tree < self::TREES; $tree++){
			$condition = self::randomTree($random, self::MAX_DEPTH);
			for($i = 0; $i < self::CONTEXTS_PER_TREE; $i++){
				$ctx = new StubContext(
					biomeId: 1 + $random->nextBoundedInt(4),
					band: $random->nextBoolean() ? SpawnBand::SURFACE : SpawnBand::CAVE,
					difficulty: $random->nextBoundedInt(4),
					feetLiquid: SpawnLiquid::from($random->nextBoundedInt(3)),
					y: $random->nextRange(-20, 80),
					light: $random->nextBoundedInt(16),
					belowItemStateId: ($random->nextBoolean() ? VanillaBlocks::GRASS() : VanillaBlocks::STONE())->asItem()->getStateId()
				);
				$key = new KeyContext($ctx->getBiomeId(), $ctx->getBand(), $ctx->getDifficulty(), $ctx->getFeetLiquid());

				$reduced = CompositeCondition::reduceCondition($condition, $key);
				$actual = is_bool($reduced) ? $reduced : $reduced->test($ctx);
				self::assertSame($condition->test($ctx), $actual, "tree $tree, context $i");

				if(is_bool($reduced)){
					$decided++;
					continue;
				}
				self::assertFalse(self::hasCacheable($reduced), "tree $tree, context $i: a cacheable condition was left undecided");
				self::assertSame($reduced, CompositeCondition::reduceCondition($reduced, $key), "tree $tree, context $i: reducing again must change nothing");
				if($reduced === $condition){
					$untouched++;
				}else{
					$partial++;
				}
			}
			if(!self::hasPerAttempt($condition)){
				self::assertIsBool(CompositeCondition::reduceCondition($condition, new KeyContext(1, SpawnBand::SURFACE, 2, SpawnLiquid::NONE)), "tree $tree reads only the key");
			}
		}

		// The generator must reach every way a reduction can end.
		self::assertGreaterThan(1000, $decided);
		self::assertGreaterThan(1000, $partial);
		self::assertGreaterThan(100, $untouched);
	}

	private static function randomTree(Random $random, int $depth) : SpawnCondition{
		if($depth === 0 || $random->nextBoundedInt(3) === 0){
			return self::randomLeaf($random);
		}
		if($random->nextBoundedInt(4) === 0){
			return new Not(self::randomTree($random, $depth - 1));
		}
		$children = [];
		for($i = $random->nextBoundedInt(4); $i >= 0; $i--){
			$children[] = self::randomTree($random, $depth - 1);
		}

		return $random->nextBoolean() ? new AllOf($children) : new AnyOf($children);
	}

	private static function randomLeaf(Random $random) : SpawnCondition{
		$min = $random->nextBoundedInt(3);

		return match($random->nextBoundedInt(6)){
			0 => new BiomeTagCondition(self::$tags, self::TAGS[$random->nextBoundedInt(3)]),
			1 => new DifficultyCondition($min, $min + $random->nextBoundedInt(4 - $min)),
			2 => new BandCondition($random->nextBoolean() ? SpawnBand::SURFACE : SpawnBand::CAVE),
			3 => new HeightCondition($random->nextRange(-20, 30), $random->nextRange(30, 80)),
			4 => RangeCondition::brightness($min * 3, $min * 3 + $random->nextBoundedInt(8)),
			default => new SpawnsOnBlock([VanillaBlocks::GRASS()->asItem()->getStateId() => true], $random->nextBoolean()),
		};
	}

	private static function hasCacheable(SpawnCondition $condition) : bool{
		if($condition instanceof CacheableCondition){
			return true;
		}
		foreach(self::children($condition) as $child){
			if(self::hasCacheable($child)){
				return true;
			}
		}

		return false;
	}

	private static function hasPerAttempt(SpawnCondition $condition) : bool{
		if($condition instanceof CompositeCondition || $condition instanceof Not){
			foreach(self::children($condition) as $child){
				if(self::hasPerAttempt($child)){
					return true;
				}
			}

			return false;
		}

		return !$condition instanceof CacheableCondition;
	}

	/**
	 * @phpstan-return list<SpawnCondition>
	 */
	private static function children(SpawnCondition $condition) : array{
		$property = match(true){
			$condition instanceof CompositeCondition => new \ReflectionProperty(CompositeCondition::class, "conditions"),
			$condition instanceof Not => new \ReflectionProperty(Not::class, "condition"),
			default => null,
		};
		if($property === null){
			return [];
		}
		$value = $property->getValue($condition);
		$children = [];
		foreach(is_array($value) ? $value : [$value] as $child){
			self::assertInstanceOf(SpawnCondition::class, $child);
			$children[] = $child;
		}

		return $children;
	}
}
