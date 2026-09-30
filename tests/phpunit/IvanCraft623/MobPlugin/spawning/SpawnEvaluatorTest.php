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

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\spawner\RegionPopulation;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\utils\Random;
use pocketmine\world\World;
use PHPUnit\Framework\TestCase;

/**
 * Filter-then-pick semantics: only matching rule sets compete, weighted by the group that
 * matched, and capped categories are left out.
 */
final class SpawnEvaluatorTest extends TestCase{
	private const TRIALS = 2000;

	/** Difficulty of every fixture position. */
	private const DIFFICULTY = World::DIFFICULTY_NORMAL;

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
		$evaluator = new SpawnEvaluator(new Random(1), MobCategoryRegistry::getInstance());
		$candidate = self::candidate([
			self::rules("minecraft:never", "a", [self::group([self::neverMatches()], 1000)]),
			self::rules("minecraft:always", "b", [self::group([], 1)]),
		]);

		for($i = 0; $i < self::TRIALS; $i++){
			$request = $evaluator->evaluateOne($candidate);
			self::assertNotNull($request, "a matching rule set must always be picked when the heavy one fails");
			self::assertSame("minecraft:always", $request->rules->getIdentifier());
		}
	}

	public function testPickWeightIsTheMatchedGroupsWeight() : void{
		$evaluator = new SpawnEvaluator(new Random(2), MobCategoryRegistry::getInstance());
		$candidate = self::candidate([
			// A heavy group that never matches must not inflate its rule set's share.
			self::rules("minecraft:split", "a", [
				self::group([self::neverMatches()], 1000),
				self::group([], 1),
			]),
			self::rules("minecraft:plain", "b", [self::group([], 1)]),
		]);

		$split = 0;
		for($i = 0; $i < self::TRIALS; $i++){
			$request = $evaluator->evaluateOne($candidate);
			self::assertNotNull($request);
			if($request->rules->getIdentifier() === "minecraft:split"){
				$split++;
			}
		}
		self::assertEqualsWithDelta(0.5, $split / self::TRIALS, 0.05);
	}

	public function testCappedCategoryDoesNotCompete() : void{
		$evaluator = new SpawnEvaluator(new Random(3), MobCategoryRegistry::getInstance());
				$candidate = self::candidate(
			[
				self::rules("minecraft:capped", "full", [self::group([], 1000)]),
				self::rules("minecraft:free", "free", [self::group([], 1)]),
			],
			new RegionPopulation([SpawnBand::SURFACE->value => ["full" => 2]])
		);

		for($i = 0; $i < self::TRIALS; $i++){
			$request = $evaluator->evaluateOne($candidate);
			self::assertNotNull($request);
			self::assertSame("minecraft:free", $request->rules->getIdentifier());
		}
	}

	public function testCapRollScalesWithFreeRoom() : void{
		$evaluator = new SpawnEvaluator(new Random(4), MobCategoryRegistry::getInstance());
		$candidate = self::candidate(
			[self::rules("minecraft:mob", "m", [self::group([], 1)])],
			new RegionPopulation([SpawnBand::SURFACE->value => ["m" => 3]], [SpawnBand::SURFACE->value => ["minecraft:mob" => 2]])
		);

		$accepted = 0;
		for($i = 0; $i < self::TRIALS * 2; $i++){
			$request = $evaluator->evaluateOne($candidate);
			if($request !== null){
				$accepted++;
				self::assertSame(3, $request->categoryCount);
				self::assertSame(2, $request->densityCount);
			}
		}
		self::assertEqualsWithDelta(0.25, $accepted / (self::TRIALS * 2), 0.04); // (4 - 3) / 4
	}

	public function testNothingMatchesYieldsNoRequest() : void{
		$evaluator = new SpawnEvaluator(new Random(5), MobCategoryRegistry::getInstance());
		$candidate = self::candidate([
			self::rules("minecraft:never", "a", [self::group([self::neverMatches()], 1)]),
		]);

		self::assertNull($evaluator->evaluateOne($candidate));
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
		return new SpawnRules($identifier, $categoryId, $groups, static fn() : Entity => throw new \LogicException("the evaluator never spawns"));
	}

	/**
	 * @phpstan-param non-empty-list<SpawnRules> $viable
	 */
	private static function candidate(array $viable, RegionPopulation $population = new RegionPopulation()) : SpawnCandidate{
		return new SpawnCandidate(
			new SpawnPosition(
				worldId: 1,
				x: 0,
				y: 65,
				z: 0,
				groundY: 64,
				band: SpawnBand::SURFACE,
				biomeId: 1,
				light: 15,
				feetTypeId: BlockTypeIds::AIR,
				belowTypeId: BlockTypeIds::GRASS,
				difficulty: self::DIFFICULTY,
				nearestPlayerDistance: 30.0,
				time: 0
			),
			$population,
			$viable
		);
	}
}
