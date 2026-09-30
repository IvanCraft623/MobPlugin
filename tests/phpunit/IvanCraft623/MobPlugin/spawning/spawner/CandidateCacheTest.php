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

use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\AnyOf;
use IvanCraft623\MobPlugin\spawning\condition\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\condition\DensityLimitCondition;
use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SlimeChunkCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\utils\Random;
use pocketmine\world\World;
use function array_keys;
use function count;
use function dirname;

final class CandidateCacheTest extends TestCase{
	private const ATTEMPTS = 5000;

	public function testMatchesUncachedEvaluationOnVanillaRules() : void{
		$root = dirname(__DIR__, 6);
		$tags = BiomeTagMap::fromFiles($root . "/vendor/pocketmine/bedrock-data/biome_id_map.json", $root . "/vendor/pocketmine/bedrock-data/biome_definitions.json");
		$rules = [];
		foreach(SpawnRulesParser::createVanilla($tags)->parseFile($root . "/resources/spawning/spawn_rules.json") as $identifier => [$categoryId, $groups]){
			$rules[] = self::rules($identifier, $groups, $categoryId);
		}
		// The registry's slime workaround: a combinator mixing key and point reads.
		$rules[] = self::rules("minecraft:slime_like", [new SpawnRuleGroup([new AnyOf([
			new AllOf([RangeCondition::height(null, 40), new SlimeChunkCondition()]),
			new BiomeTagCondition($tags, ["spawns_slimes_on_surface"], []),
		])])]);

		$biomeIds = [];
		for($id = 0; $id < 400; $id++){
			if($tags->getTags($id) !== []){
				$biomeIds[] = $id;
			}
		}
		self::assertNotEmpty($biomeIds);
		$belowTypeIds = [BlockTypeIds::GRASS, BlockTypeIds::SAND, BlockTypeIds::STONE, BlockTypeIds::MYCELIUM, BlockTypeIds::PODZOL, BlockTypeIds::SNOW, BlockTypeIds::ICE, BlockTypeIds::NETHERRACK, BlockTypeIds::SOUL_SAND, BlockTypeIds::RED_SAND, BlockTypeIds::DEEPSLATE];
		$identifiers = [];
		foreach($rules as $r){
			$identifiers[] = $r->getIdentifier();
		}

		$cache = new CandidateCache($rules);
		$random = new Random(1234);
		$matched = 0;
		for($i = 0; $i < self::ATTEMPTS; $i++){
			$band = $random->nextBoundedInt(2) === 0 ? SpawnBand::SURFACE : SpawnBand::CAVE;
			$counts = [];
			foreach($identifiers as $identifier){
				$counts[$identifier] = $random->nextBoundedInt(6);
			}
			$ctx = new StubContext(
				biomeId: $biomeIds[$random->nextBoundedInt(count($biomeIds))],
				band: $band,
				difficulty: $random->nextBoundedInt(4),
				feetLiquid: SpawnLiquid::from($random->nextBoundedInt(3)),
				x: $random->nextRange(-5000, 5000),
				y: $random->nextRange(-64, 200),
				z: $random->nextRange(-5000, 5000),
				light: $random->nextBoundedInt(16),
				weatherLightPenalty: $random->nextBoundedInt(4),
				belowTypeId: $belowTypeIds[$random->nextBoundedInt(count($belowTypeIds))],
				nearestPlayerDistance: $random->nextFloat() * 128,
				time: $random->nextBoundedInt(2000000),
				population: new RegionPopulation([], [$band->value => $counts])
			);

			$actual = [];
			foreach($cache->getCandidates($ctx) as $candidate){
				$group = $candidate->match($ctx);
				if($group !== null){
					$actual[$candidate->getRules()->getIdentifier()] = $group;
				}
			}
			$expected = [];
			foreach($rules as $r){
				$group = $r->check($ctx);
				if($group !== null){
					$expected[$r->getIdentifier()] = $group;
				}
			}
			self::assertSame(array_keys($expected), array_keys($actual), "attempt $i");
			foreach($expected as $identifier => $group){
				self::assertSame($group, $actual[$identifier], "$identifier at attempt $i");
			}
			$matched += count($expected);
		}
		self::assertGreaterThan(self::ATTEMPTS, $matched, "the random contexts must exercise real matches");
	}

	public function testNonCacheableConditionRunsOnEveryAttemptAndNeverOnTheKey() : void{
		$spy = new SpyCondition(cacheable: false);
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([$spy])])]);
		for($i = 0; $i < 10; $i++){
			$ctx = new StubContext();
			foreach($cache->getCandidates($ctx) as $candidate){
				$candidate->match($ctx);
			}
		}
		self::assertSame(10, $spy->calls);
		self::assertSame(0, $spy->keyContextCalls);
	}

	public function testKeyOnlyConditionRunsOncePerKey() : void{
		$spy = new SpyCondition(cacheable: true, readsBiomeOnly: true);
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([$spy])])]);
		for($i = 0; $i < 10; $i++){
			foreach([1, 2] as $biomeId){
				$ctx = new StubContext(biomeId: $biomeId, y: $i);
				foreach($cache->getCandidates($ctx) as $candidate){
					self::assertNotNull($candidate->match($ctx));
				}
			}
		}
		self::assertSame(2, $spy->calls);
		self::assertSame(2, $cache->getSize());
	}

	public function testLiquidKeyOnlyAdmitsGroupsRequiringThatLiquid() : void{
		$land = self::rules("minecraft:land", [new SpawnRuleGroup([])]);
		$fish = self::rules("minecraft:fish", [new SpawnRuleGroup([RangeCondition::liquid(SpawnLiquid::WATER)])]);
		$cache = new CandidateCache([$land, $fish]);

		self::assertSame(["minecraft:fish"], self::identifiers($cache->getCandidates(new StubContext(feetLiquid: SpawnLiquid::WATER))));
		self::assertSame([], self::identifiers($cache->getCandidates(new StubContext(feetLiquid: SpawnLiquid::LAVA))));
		self::assertSame(["minecraft:land"], self::identifiers($cache->getCandidates(new StubContext())));

		// The uncached reference applies the same gate.
		self::assertNull($land->check(new StubContext(feetLiquid: SpawnLiquid::WATER)));
		self::assertNotNull($fish->check(new StubContext(feetLiquid: SpawnLiquid::WATER)));
		self::assertNull($fish->check(new StubContext()));
	}

	public function testSizeStaysWithinMaxKeys() : void{
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([])])], maxKeys: 4);
		for($biomeId = 0; $biomeId < 5; $biomeId++){
			$cache->getCandidates(new StubContext(biomeId: $biomeId));
			self::assertLessThanOrEqual(4, $cache->getSize());
		}
	}

	public function testIdenticalOutcomesShareOneList() : void{
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([RangeCondition::height(0, 10)])])]);
		$easy = $cache->getCandidates(new StubContext(difficulty: 1));
		$hard = $cache->getCandidates(new StubContext(difficulty: 3));
		self::assertSame($easy, $hard);
		self::assertSame(2, $cache->getSize());
	}

	public function testKeyPartsFitTheirBits() : void{
		foreach(SpawnLiquid::cases() as $liquid){
			self::assertLessThan(4, $liquid->value);
		}
		foreach(SpawnBand::cases() as $band){
			self::assertLessThan(2, $band->value);
		}
	}

	public function testOutOfRangeKeyThrows() : void{
		$cache = new CandidateCache([]);
		foreach([new StubContext(difficulty: -1), new StubContext(difficulty: 4), new StubContext(biomeId: -1)] as $ctx){
			try{
				$cache->getCandidates($ctx);
				self::fail("an out-of-range key must throw");
			}catch(\InvalidArgumentException){
				self::addToAssertionCount(1);
			}
		}
	}

	public function testPopulationReadersRunLast() : void{
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([
			new DensityLimitCondition("minecraft:a", 5, null),
			RangeCondition::brightness(0, 7),
		])])]);

		$bright = new StubContext(light: 15);
		foreach($cache->getCandidates($bright) as $candidate){
			self::assertNull($candidate->match($bright));
		}
		self::assertSame(0, $bright->populationReads, "a failing light check spares the census");

		$dark = new StubContext(light: 3);
		foreach($cache->getCandidates($dark) as $candidate){
			self::assertNotNull($candidate->match($dark));
		}
		self::assertSame(1, $dark->populationReads);
	}

	public function testUnclassifiedResidualsKeepDataOrder() : void{
		$spy = new SpyCondition(cacheable: false);
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([$spy, RangeCondition::brightness(0, 7)])])]);
		$ctx = new StubContext(light: 15);
		foreach($cache->getCandidates($ctx) as $candidate){
			self::assertNull($candidate->match($ctx));
		}
		self::assertSame(1, $spy->calls, "the non-cacheable condition still runs before the brightness check");
	}

	public function testWorldReadingConditionRunsOnEveryAttempt() : void{
		$condition = new class implements SpawnCondition{
			/** @phpstan-var list<World> */
			public array $seen = [];

			public function isCacheable() : bool{
				return true;
			}

			public function test(SpawnConditionContext $ctx) : bool{
				$this->seen[] = $ctx->getWorld();

				return true;
			}
		};
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([$condition])])]);
		$world = $this->createMock(World::class);
		for($i = 0; $i < 3; $i++){
			$ctx = new StubContext(world: $world);
			foreach($cache->getCandidates($ctx) as $candidate){
				self::assertNotNull($candidate->match($ctx));
			}
		}
		self::assertSame([$world, $world, $world], $condition->seen, "kept as a residual and given the attempt's world");
	}

	public function testThrowingConditionLeavesNoEntry() : void{
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([new SpyCondition(cacheable: true, throws: true)])])]);
		for($i = 0; $i < 2; $i++){
			try{
				$cache->getCandidates(new StubContext());
				self::fail("the condition's exception must propagate");
			}catch(\RuntimeException){
				self::assertSame(0, $cache->getSize());
			}
		}
	}

	/**
	 * @phpstan-param list<SpawnRuleGroup> $groups
	 */
	private static function rules(string $identifier, array $groups, string $categoryId = "monster") : SpawnRules{
		return new SpawnRules($identifier, $categoryId, $groups, static fn() : Entity => throw new \LogicException("never spawned"));
	}

	/**
	 * @phpstan-param list<CandidateRule> $candidates
	 * @phpstan-return list<string>
	 */
	private static function identifiers(array $candidates) : array{
		$result = [];
		foreach($candidates as $candidate){
			$result[] = $candidate->getRules()->getIdentifier();
		}

		return $result;
	}
}
