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

use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\MobCategory;
use IvanCraft623\MobPlugin\spawning\MobCategoryRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\utils\Random;
use pocketmine\world\World;
use function array_map;

final class SpawnSelectorTest extends TestCase{
	private const TRIALS = 2000;

	/** Category id => surface and cave cap. */
	private const CATEGORIES = ["a" => 100, "b" => 100, "free" => 100, "full" => 2, "m" => 4];

	protected function setUp() : void{
		foreach(self::CATEGORIES as $id => $cap){
			MobCategoryRegistry::getInstance()->register(new MobCategory($id, $cap, $cap, 64));
		}
	}

	protected function tearDown() : void{
		foreach(self::CATEGORIES as $id => $_){
			MobCategoryRegistry::getInstance()->unregister($id);
		}
	}

	public function testFailingRuleDoesNotWasteTheAttempt() : void{
		$selector = self::selector(1);
		$candidates = self::candidates([
			self::rules("minecraft:never", "a", [self::group([self::neverMatches()], 1000)]),
			self::rules("minecraft:always", "b", [self::group([], 1)]),
		]);

		for($i = 0; $i < self::TRIALS; $i++){
			self::assertSame("minecraft:always", self::selectIdentifier($selector, new StubContext(), $candidates));
		}
	}

	public function testPickWeightIsTheMatchedGroupsWeight() : void{
		$selector = self::selector(2);
		$candidates = self::candidates([
			// A heavy group that never matches must not inflate its rule's share.
			self::rules("minecraft:split", "a", [self::group([self::neverMatches()], 1000), self::group([], 1)]),
			self::rules("minecraft:plain", "b", [self::group([], 1)]),
		]);

		$split = 0;
		for($i = 0; $i < self::TRIALS; $i++){
			if(self::selectIdentifier($selector, new StubContext(), $candidates) === "minecraft:split"){
				$split++;
			}
		}
		self::assertEqualsWithDelta(0.5, $split / self::TRIALS, 0.05);
	}

	public function testCappedCategoryDoesNotCompete() : void{
		$selector = self::selector(3);
		$candidates = self::candidates([
			self::rules("minecraft:capped", "full", [self::group([], 1000)]),
			self::rules("minecraft:free", "free", [self::group([], 1)]),
		]);
		$ctx = new StubContext(population: new RegionPopulation([SpawnBand::SURFACE->value => ["full" => 2]]));

		for($i = 0; $i < self::TRIALS; $i++){
			self::assertSame("minecraft:free", self::selectIdentifier($selector, $ctx, $candidates));
		}
	}

	public function testCapRollScalesWithFreeRoom() : void{
		$selector = self::selector(4);
		$candidates = self::candidates([self::rules("minecraft:mob", "m", [self::group([], 1)])]);
		$ctx = new StubContext(population: new RegionPopulation([SpawnBand::SURFACE->value => ["m" => 3]]));

		$accepted = 0;
		for($i = 0; $i < self::TRIALS * 2; $i++){
			if($selector->select($ctx, $candidates) !== null){
				$accepted++;
			}
		}
		self::assertEqualsWithDelta(0.25, $accepted / (self::TRIALS * 2), 0.04); // (4 - 3) / 4
	}

	public function testNothingMatchesYieldsNothing() : void{
		$candidates = self::candidates([self::rules("minecraft:never", "a", [self::group([self::neverMatches()], 1)])]);
		self::assertNull(self::selector(5)->select(new StubContext(), $candidates));
	}

	public function testUnregisteredCategoryIsSkipped() : void{
		$candidates = self::candidates([self::rules("minecraft:orphan", "no_such_category", [self::group([], 1)])]);
		self::assertNull(self::selector(6)->select(new StubContext(), $candidates));
	}

	public function testReRegisteredCategoryCapAppliesToEarlierRules() : void{
		$selector = self::selector(7);
		$candidates = self::candidates([self::rules("minecraft:mob", "a", [self::group([], 1)])]);
		$ctx = new StubContext(population: new RegionPopulation([SpawnBand::SURFACE->value => ["a" => 50]]));
		self::assertNotNull(self::selectUntilAccepted($selector, $ctx, $candidates), "under the original cap of 100");

		MobCategoryRegistry::getInstance()->register(new MobCategory("a", 10, 10, 64));
		for($i = 0; $i < self::TRIALS; $i++){
			self::assertNull($selector->select($ctx, $candidates), "over the new cap of 10");
		}
	}

	private static function selector(int $seed) : SpawnSelector{
		return new SpawnSelector(new Random($seed), MobCategoryRegistry::getInstance());
	}

	/**
	 * @phpstan-param list<CandidateRule> $candidates
	 */
	private static function selectIdentifier(SpawnSelector $selector, StubContext $ctx, array $candidates) : string{
		$selected = $selector->select($ctx, $candidates);
		self::assertNotNull($selected, "a free category with a match is always accepted");

		return $selected[0]->getRules()->getIdentifier();
	}

	/**
	 * @phpstan-param list<CandidateRule> $candidates
	 * @phpstan-return array{CandidateRule, SpawnRuleGroup}|null
	 */
	private static function selectUntilAccepted(SpawnSelector $selector, StubContext $ctx, array $candidates) : ?array{
		for($i = 0; $i < 100; $i++){
			$selected = $selector->select($ctx, $candidates);
			if($selected !== null){
				return $selected;
			}
		}

		return null;
	}

	private static function neverMatches() : SpawnCondition{
		return RangeCondition::difficulty(World::DIFFICULTY_HARD, World::DIFFICULTY_HARD);
	}

	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 */
	private static function group(array $conditions, int $weight) : SpawnRuleGroup{
		return new SpawnRuleGroup($conditions, $weight);
	}

	/**
	 * @phpstan-param list<SpawnRuleGroup> $groups
	 */
	private static function rules(string $identifier, string $categoryId, array $groups) : SpawnRules{
		return new SpawnRules($identifier, $categoryId, $groups, static fn() : Entity => throw new \LogicException("the selector never spawns"));
	}

	/**
	 * Every condition left as a residual, so the selector does all the matching.
	 *
	 * @phpstan-param list<SpawnRules> $rules
	 * @phpstan-return list<CandidateRule>
	 */
	private static function candidates(array $rules) : array{
		return array_map(static fn(SpawnRules $r) : CandidateRule => new CandidateRule(
			$r,
			array_map(static fn(SpawnRuleGroup $group) : array => [$group, $group->getConditions()], $r->getGroups())
		), $rules);
	}
}
