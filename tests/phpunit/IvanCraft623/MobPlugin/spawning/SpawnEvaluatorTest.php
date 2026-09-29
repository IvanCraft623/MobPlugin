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
use IvanCraft623\MobPlugin\spawning\condition\vanilla\DifficultyFilter;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;
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

	public function testFailingRuleDoesNotWasteTheAttempt() : void{
		$evaluator = new SpawnEvaluator(new Random(1));
		$candidate = self::candidate([
			self::binding("minecraft:never", self::category("a"), [self::group([self::neverMatches()], 1000)]),
			self::binding("minecraft:always", self::category("b"), [self::group([], 1)]),
		]);

		for($i = 0; $i < self::TRIALS; $i++){
			$request = $evaluator->evaluateOne($candidate);
			self::assertNotNull($request, "a matching rule set must always be picked when the heavy one fails");
			self::assertSame("minecraft:always", $request->binding->getRules()->getIdentifier());
		}
	}

	public function testPickWeightIsTheMatchedGroupsWeight() : void{
		$evaluator = new SpawnEvaluator(new Random(2));
		$candidate = self::candidate([
			// A heavy group that never matches must not inflate its rule set's share.
			self::binding("minecraft:split", self::category("a"), [
				self::group([self::neverMatches()], 1000),
				self::group([], 1),
			]),
			self::binding("minecraft:plain", self::category("b"), [self::group([], 1)]),
		]);

		$split = 0;
		for($i = 0; $i < self::TRIALS; $i++){
			$request = $evaluator->evaluateOne($candidate);
			self::assertNotNull($request);
			if($request->binding->getRules()->getIdentifier() === "minecraft:split"){
				$split++;
			}
		}
		self::assertEqualsWithDelta(0.5, $split / self::TRIALS, 0.05);
	}

	public function testCappedCategoryDoesNotCompete() : void{
		$evaluator = new SpawnEvaluator(new Random(3));
		$full = self::category("full", 2);
		$candidate = self::candidate(
			[
				self::binding("minecraft:capped", $full, [self::group([], 1000)]),
				self::binding("minecraft:free", self::category("free"), [self::group([], 1)]),
			],
			new SpawnCounts([], ["full" => new BandCounts(2, 0)])
		);

		for($i = 0; $i < self::TRIALS; $i++){
			$request = $evaluator->evaluateOne($candidate);
			self::assertNotNull($request);
			self::assertSame("minecraft:free", $request->binding->getRules()->getIdentifier());
		}
	}

	public function testCapRollScalesWithFreeRoom() : void{
		$evaluator = new SpawnEvaluator(new Random(4));
		$candidate = self::candidate(
			[self::binding("minecraft:mob", self::category("m", 4), [self::group([], 1)])],
			new SpawnCounts(["minecraft:mob" => new BandCounts(2, 0)], ["m" => new BandCounts(3, 0)])
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
		$evaluator = new SpawnEvaluator(new Random(5));
		$candidate = self::candidate([
			self::binding("minecraft:never", self::category("a"), [self::group([self::neverMatches()], 1)]),
		]);

		self::assertNull($evaluator->evaluateOne($candidate));
	}

	private static function neverMatches() : SpawnCondition{
		return new DifficultyFilter(World::DIFFICULTY_HARD, World::DIFFICULTY_HARD);
	}

	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 */
	private static function group(array $conditions, int $weight) : SpawnConditionGroup{
		return new SpawnConditionGroup($conditions, $weight);
	}

	private static function category(string $id, int $surfaceCap = 100) : MobCategory{
		return new MobCategory($id, new BandCounts($surfaceCap, $surfaceCap), 64);
	}

	/**
	 * @phpstan-param list<SpawnConditionGroup> $groups
	 */
	private static function binding(string $identifier, MobCategory $category, array $groups) : SpawnRuleBinding{
		return new SpawnRuleBinding(
			new SpawnRules($identifier, $category->id, $groups),
			static fn() : Entity => throw new \LogicException("the evaluator never spawns"),
			$category
		);
	}

	/**
	 * @phpstan-param non-empty-list<SpawnRuleBinding> $viable
	 */
	private static function candidate(array $viable, SpawnCounts $counts = new SpawnCounts()) : SpawnCandidate{
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
			$counts,
			$viable
		);
	}
}
