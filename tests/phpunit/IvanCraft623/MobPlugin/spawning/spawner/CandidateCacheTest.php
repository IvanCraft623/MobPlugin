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
use IvanCraft623\MobPlugin\spawning\condition\BandCondition;
use IvanCraft623\MobPlugin\spawning\condition\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\condition\BrightnessCondition;
use IvanCraft623\MobPlugin\spawning\condition\DifficultyCondition;
use IvanCraft623\MobPlugin\spawning\condition\HeightCondition;
use IvanCraft623\MobPlugin\spawning\condition\LightChanceCondition;
use IvanCraft623\MobPlugin\spawning\condition\Not;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\utils\Random;
use function array_reverse;
use function count;

/**
 * The cache must return what plain evaluation returns: a wrong cache gives wrong spawns.
 */
final class CandidateCacheTest extends TestCase{
	private const POINTS_PER_KEY = 6;

	/**
	 * One rule set per thing the cache does to a rule.
	 *
	 * @phpstan-return list<SpawnRules>
	 */
	private static function rules() : array{
		BiomeTagMap::setInstance(new BiomeTagMap([1 => ["warm" => true], 2 => ["cold" => true], 3 => ["warm" => true, "wet" => true]]));
		$warm = new BiomeTagCondition("warm");
		$cold = new BiomeTagCondition("cold");
		$wet = new BiomeTagCondition("wet");
		$dark = new BrightnessCondition(0, 7);
		$deep = new HeightCondition(null, 40);

		$sets = [
			// Decided by the key alone.
			"cacheable" => [
				new SpawnRuleGroup([$warm, new DifficultyCondition(1, 3)]),
				new SpawnRuleGroup([new BandCondition(SpawnBand::CAVE)]),
			],
			// Left for every attempt.
			"residual" => [
				new SpawnRuleGroup([$dark, $deep]),
			],
			// Combinators mixing both kinds, which the key decides only in part.
			"combinators" => [
				new SpawnRuleGroup([new AllOf([$warm, $deep])]),
				new SpawnRuleGroup([new AnyOf([new BandCondition(SpawnBand::SURFACE), $dark])]),
				new SpawnRuleGroup([new Not(new AnyOf([$cold, $dark]))]),
				new SpawnRuleGroup([new AnyOf([new AllOf([$wet, $deep]), new AllOf([new Not($warm), new Not($dark)])])]),
			],
			"liquids" => [
				new SpawnRuleGroup([$warm], requiredLiquid: SpawnLiquid::WATER),
				new SpawnRuleGroup([$dark], requiredLiquid: SpawnLiquid::LAVA),
			],
			// Chances are rolled by both sides in the same order.
			"chances" => [
				new SpawnRuleGroup([$wet, new LightChanceCondition(8)]),
				new SpawnRuleGroup([new AnyOf([
					new AllOf([$deep, new LightChanceCondition(8)]),
					new AllOf([$cold, new LightChanceCondition(8, inverted: true)]),
				])]),
			],
		];

		$rules = [];
		foreach($sets as $identifier => $groups){
			$rules[] = new SpawnRules($identifier, "monster", $groups, static fn() : Entity => throw new \LogicException("never spawned"), new EntitySizeInfo(1.0, 1.0));
		}

		return $rules;
	}

	/**
	 * @phpstan-return list<array{int, SpawnBand, int, SpawnLiquid}>
	 */
	private static function keys() : array{
		$keys = [];
		foreach([1, 2, 3, 4] as $biomeId){
			foreach(SpawnBand::cases() as $band){
				for($difficulty = 0; $difficulty < 4; $difficulty++){
					foreach(SpawnLiquid::cases() as $liquid){
						$keys[] = [$biomeId, $band, $difficulty, $liquid];
					}
				}
			}
		}

		return $keys;
	}

	/**
	 * @phpstan-return iterable<string, array{bool}>
	 */
	public static function keyOrders() : iterable{
		yield "in order" => [false];
		yield "reversed" => [true];
	}

	/**
	 * Keys with equal results share them, so the keys are resolved in two orders: the
	 * outcome of a key must not depend on what was resolved before it.
	 *
	 * @dataProvider keyOrders
	 */
	public function testEveryKeyMatchesPlainEvaluation(bool $reversed) : void{
		$rules = self::rules();
		$keys = $reversed ? array_reverse(self::keys()) : self::keys();
		$cache = new CandidateCache($rules);
		$random = new Random(4242);
		$matches = [];
		foreach($keys as [$biomeId, $band, $difficulty, $liquid]){
			for($point = 0; $point < self::POINTS_PER_KEY; $point++){
				$arguments = [
					"biomeId" => $biomeId,
					"band" => $band,
					"difficulty" => $difficulty,
					"feetLiquid" => $liquid,
					"y" => $random->nextRange(20, 60),
					"light" => $random->nextBoundedInt(16),
				];
				// Each side rolls from its own equally seeded source: a chance drawn out of
				// step shows as a different outcome.
				$seed = $random->nextInt();
				$cached = new StubContext(...$arguments, random: new Random($seed));
				$plain = new StubContext(...$arguments, random: new Random($seed));

				$actual = [];
				foreach($cache->getCandidates($cached) as $candidate){
					$groups = $candidate->match($cached);
					if(count($groups) !== 0){
						$actual[$candidate->getRules()->getIdentifier()] = $groups;
					}
				}
				$expected = [];
				foreach($rules as $spawnRules){
					$groups = $spawnRules->check($plain);
					if(count($groups) !== 0){
						$expected[$spawnRules->getIdentifier()] = $groups;
					}
				}

				$where = "biome $biomeId, $band->name, difficulty $difficulty, $liquid->name, point $point";
				self::assertSame($expected, $actual, $where);
				self::assertSame($plain->random->nextInt(), $cached->random->nextInt(), "both sides drew the same rolls ($where)");
				$matches += $expected;
			}
		}
		self::assertCount(count($rules), $matches, "every rule set must match somewhere");
	}
}
