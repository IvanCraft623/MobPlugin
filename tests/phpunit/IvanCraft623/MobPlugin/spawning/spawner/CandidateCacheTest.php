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

use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;

final class CandidateCacheTest extends TestCase{
	public function testPlainConditionRunsOnEveryAttempt() : void{
		$spy = new SpyCondition();
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([$spy])])]);
		for($i = 0; $i < 10; $i++){
			$ctx = new StubContext();
			foreach($cache->getCandidates($ctx) as $candidate){
				$candidate->match($ctx);
			}
		}
		self::assertSame(10, $spy->calls);
	}

	public function testCacheableConditionRunsOncePerKey() : void{
		$spy = new CacheableSpyCondition();
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([$spy])])]);
		for($i = 0; $i < 10; $i++){
			foreach([1, 2] as $biomeId){
				$ctx = new StubContext(biomeId: $biomeId, y: $i);
				foreach($cache->getCandidates($ctx) as $candidate){
					self::assertNotSame([], $candidate->match($ctx));
				}
			}
		}
		self::assertSame(2, $spy->calls);
	}

	public function testAllOfKeepsOnlyItsUndecidedChildren() : void{
		$inBiome = new CacheableSpyCondition(biomeId: 1);
		$perAttempt = new SpyCondition();
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([new AllOf([$inBiome, $perAttempt])])])]);

		self::assertSame([], $cache->getCandidates(new StubContext(biomeId: 2)), "a failed cacheable child drops the group");
		for($i = 0; $i < 5; $i++){
			$ctx = new StubContext(biomeId: 1);
			foreach($cache->getCandidates($ctx) as $candidate){
				self::assertNotSame([], $candidate->match($ctx));
			}
		}
		self::assertSame(2, $inBiome->calls, "once per key");
		self::assertSame(5, $perAttempt->calls);
	}

	public function testLiquidKeyOnlyAdmitsGroupsRequiringThatLiquid() : void{
		$land = self::rules("minecraft:land", [new SpawnRuleGroup([])]);
		$fish = self::rules("minecraft:fish", [new SpawnRuleGroup([], requiredLiquid: SpawnLiquid::WATER)]);
		$cache = new CandidateCache([$land, $fish]);

		self::assertSame(["minecraft:fish"], self::identifiers($cache->getCandidates(new StubContext(feetLiquid: SpawnLiquid::WATER))));
		self::assertSame([], self::identifiers($cache->getCandidates(new StubContext(feetLiquid: SpawnLiquid::LAVA))));
		self::assertSame(["minecraft:land"], self::identifiers($cache->getCandidates(new StubContext())));

		// The uncached reference applies the same gate.
		self::assertSame([], $land->check(new StubContext(feetLiquid: SpawnLiquid::WATER)));
		self::assertNotSame([], $fish->check(new StubContext(feetLiquid: SpawnLiquid::WATER)));
		self::assertSame([], $fish->check(new StubContext()));
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

	public function testResidualsKeepDataOrder() : void{
		$spy = new SpyCondition();
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([$spy, RangeCondition::brightness(0, 7)])])]);
		$ctx = new StubContext(light: 15);
		foreach($cache->getCandidates($ctx) as $candidate){
			self::assertSame([], $candidate->match($ctx));
		}
		self::assertSame(1, $spy->calls, "the first condition still runs before the brightness check");
	}

	public function testThrowingConditionLeavesNoEntry() : void{
		$cache = new CandidateCache([self::rules("minecraft:a", [new SpawnRuleGroup([new CacheableSpyCondition(throws: true)])])]);
		for($i = 0; $i < 2; $i++){
			try{
				$cache->getCandidates(new StubContext());
				self::fail("the condition's exception must propagate");
			}catch(\RuntimeException){
				// Thrown again on the second read: nothing was cached.
				self::addToAssertionCount(1);
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
