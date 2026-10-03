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
use IvanCraft623\MobPlugin\spawning\condition\CacheableConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\HeightCondition;
use IvanCraft623\MobPlugin\spawning\condition\LightChanceCondition;
use IvanCraft623\MobPlugin\spawning\condition\MoonPhaseChanceCondition;
use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SlimeChunkCondition;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\utils\Random;
use function array_keys;
use function array_map;
use function count;
use function dirname;
use function sort;
use function spl_object_id;

/**
 * The cache against plain evaluation over the whole vanilla key space, with the rules as
 * the registry builds them: chance rolls included, results shared between keys.
 */
final class CandidateCacheTest extends TestCase{
	private const POINTS_PER_KEY = 8;

	/** @phpstan-var list<int> item state ids of the blocks a position stands on */
	private static array $below;

	/** @phpstan-var list<SpawnRules> */
	private static array $rules;

	/** @phpstan-var list<array{int, SpawnBand, int, SpawnLiquid}> every key of the vanilla key space */
	private static array $keys;

	public static function setUpBeforeClass() : void{
		self::$below = array_map(
			static fn(Block $block) : int => $block->asItem()->getStateId(),
			[VanillaBlocks::GRASS(), VanillaBlocks::SAND(), VanillaBlocks::STONE(), VanillaBlocks::MYCELIUM(), VanillaBlocks::SNOW(), VanillaBlocks::NETHERRACK(), VanillaBlocks::DEEPSLATE()]
		);
		$root = dirname(__DIR__, 6);
		$tags = BiomeTagMap::fromFiles($root . "/vendor/pocketmine/bedrock-data/biome_id_map.json", $root . "/vendor/pocketmine/bedrock-data/biome_definitions.json");

		// As SpawnRuleRegistry::registerVanilla() builds them; it tells monsters by their
		// entity class, here by their category.
		self::$rules = [];
		foreach(SpawnRulesParser::createVanilla($tags)->parseFile($root . "/resources/spawning/spawn_rules.json") as $identifier => [$categoryId, $groups]){
			$hardcoded = [];
			if($identifier === "minecraft:slime"){
				$hardcoded[] = new AnyOf([
					new AllOf([new HeightCondition(null, 38), new SlimeChunkCondition()]),
					new AllOf([
						new HeightCondition(50, 68),
						new BiomeTagCondition($tags, "spawns_slimes_on_surface"),
						new LightChanceCondition(8, inverted: true),
						new MoonPhaseChanceCondition(),
					]),
				]);
			}
			if($categoryId === "monster"){
				$hardcoded[] = RangeCondition::blockLight(0, 0);
			}
			$groups = array_map(static fn(SpawnRuleGroup $group) : SpawnRuleGroup => $group->withConditions($hardcoded), $groups);
			self::$rules[] = new SpawnRules($identifier, $categoryId, $groups, static fn() : Entity => throw new \LogicException("never spawned"));
		}

		self::$keys = [];
		for($biomeId = 0; $biomeId < 400; $biomeId++){
			if($tags->getTags($biomeId) === []){
				continue;
			}
			foreach(SpawnBand::cases() as $band){
				for($difficulty = 0; $difficulty < 4; $difficulty++){
					foreach(SpawnLiquid::cases() as $liquid){
						self::$keys[] = [$biomeId, $band, $difficulty, $liquid];
					}
				}
			}
		}
		self::assertGreaterThan(1000, count(self::$keys));
	}

	/**
	 * @phpstan-return iterable<string, array{int|null}>
	 */
	public static function keyOrders() : iterable{
		yield "in order" => [null];
		yield "shuffled" => [20240];
	}

	/**
	 * Shared results make a key's outcome depend on what was resolved before it only if
	 * sharing is wrong, so the keys are resolved in two orders.
	 *
	 * @dataProvider keyOrders
	 */
	public function testEveryKeyMatchesPlainEvaluation(?int $shuffleSeed) : void{
		$keys = self::$keys;
		if($shuffleSeed !== null){
			$shuffle = new Random($shuffleSeed);
			for($i = count($keys) - 1; $i > 0; $i--){
				$j = $shuffle->nextBoundedInt($i + 1);
				[$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
			}
		}

		$cache = new CandidateCache(self::$rules);
		$random = new Random(4242);
		$matched = 0;
		$slimes = 0;
		foreach($keys as $index => [$biomeId, $band, $difficulty, $liquid]){
			for($point = 0; $point < self::POINTS_PER_KEY; $point++){
				$arguments = [
					"biomeId" => $biomeId,
					"band" => $band,
					"difficulty" => $difficulty,
					"feetLiquid" => $liquid,
					"x" => $random->nextRange(-3000, 3000),
					"y" => $random->nextRange(-64, 120),
					"z" => $random->nextRange(-3000, 3000),
					"light" => $random->nextBoundedInt(16),
					"belowItemStateId" => self::$below[$random->nextBoundedInt(count(self::$below))],
					"nearestPlayerDistance" => 10 + $random->nextFloat() * 130,
					"time" => $random->nextBoundedInt(400000),
					"blockLight" => $random->nextBoundedInt(4) === 0 ? $random->nextBoundedInt(16) : 0,
				];
				// Each side rolls from its own equally seeded source: any chance drawn
				// out of step shows as a different outcome.
				$seed = $random->nextInt();
				$cached = new StubContext(...$arguments, random: new Random($seed));
				$plain = new StubContext(...$arguments, random: new Random($seed));

				$actual = [];
				foreach($cache->getCandidates($cached) as $candidate){
					$groups = $candidate->match($cached);
					if($groups !== []){
						$actual[$candidate->getRules()->getIdentifier()] = $groups;
					}
				}
				$expected = [];
				foreach(self::$rules as $rules){
					$groups = $rules->check($plain);
					if($groups !== []){
						$expected[$rules->getIdentifier()] = $groups;
					}
				}

				self::assertSame(array_keys($expected), array_keys($actual), "key $index, point $point");
				foreach($expected as $identifier => $groups){
					self::assertSame($groups, $actual[$identifier], "$identifier at key $index, point $point");
				}
				self::assertSame($plain->random->nextInt(), $cached->random->nextInt(), "both sides drew the same number of rolls (key $index, point $point)");
				$matched += count($expected);
				$slimes += isset($expected["minecraft:slime"]) ? 1 : 0;
			}
		}
		self::assertGreaterThan(count($keys), $matched, "the contexts must exercise real matches");
		self::assertGreaterThan(20, $slimes, "the contexts must exercise the slime rule");
	}

	/**
	 * Equal results are one object, not one copy per key: far fewer distinct rule entries
	 * than a copy for every rule of every key.
	 */
	public function testEqualResultsAreSharedAcrossTheKeySpace() : void{
		$cache = new CandidateCache(self::$rules);
		$distinct = [];
		$entries = 0;
		foreach(self::$keys as [$biomeId, $band, $difficulty, $liquid]){
			foreach($cache->getCandidates(new StubContext(biomeId: $biomeId, band: $band, difficulty: $difficulty, feetLiquid: $liquid)) as $candidate){
				$distinct[spl_object_id($candidate)] = $candidate; // kept alive, so ids stay unique
				$entries++;
			}
		}

		self::assertGreaterThan(count(self::$keys), $entries);
		self::assertLessThan($entries / 20, count($distinct), "$entries entries over " . count(self::$keys) . " keys");
	}

	public function testCacheableConditionRunsOncePerKey() : void{
		$spy = new CacheableSpyCondition();
		$cache = new CandidateCache([new SpawnRules("minecraft:a", "monster", [new SpawnRuleGroup([$spy])], static fn() : Entity => throw new \LogicException("never spawned"))]);
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

	/**
	 * The cache key packs exactly these values, each in its own bits. A new getter or a
	 * new enum case changes what a key means: update the hash and this test together.
	 */
	public function testTheKeyCoversEveryCachedByValue() : void{
		$getters = array_map(
			static fn(\ReflectionMethod $method) : string => $method->getName(),
			(new \ReflectionClass(CacheableConditionContext::class))->getMethods()
		);
		sort($getters);
		self::assertSame(["getBand", "getBiomeId", "getDifficulty", "getFeetLiquid"], $getters);

		foreach(SpawnLiquid::cases() as $liquid){
			self::assertLessThan(4, $liquid->value);
		}
		foreach(SpawnBand::cases() as $band){
			self::assertLessThan(2, $band->value);
		}
	}
}
