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

use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaMobCategories;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaSpawnConditions;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use PHPUnit\Framework\TestCase;
use function array_diff_key;
use function array_is_list;
use function array_keys;
use function array_map;
use function dirname;
use function file_get_contents;
use function json_decode;
use function sort;
use function str_starts_with;
use function strlen;
use function substr;
use const JSON_THROW_ON_ERROR;

/**
 * The strict loader accepts the bundled data, applying exactly its by-design
 * degradations. Expectations are derived from the raw JSON, not written by hand.
 */
final class SpawnRulesParseableTest extends TestCase{
	private const DATA_PATH = "/resources/spawning/spawn_rules.json";

	/** population_control values vanilla spawns through events, never naturally. */
	private const SKIPPED_CATEGORIES = [VanillaMobCategories::PILLAGER => true];

	private const HABITAT_MARKERS = [VanillaSpawnConditions::SPAWNS_ON_SURFACE => true, VanillaSpawnConditions::SPAWNS_UNDERGROUND => true];

	/** @phpstan-var array<string, array{string, list<array<string, mixed>>}> identifier => [population_control, raw groups] */
	private static array $raw;

	/** @phpstan-var array<string, array{string, list<SpawnRuleGroup>}> */
	private static array $parsed;

	public static function setUpBeforeClass() : void{
		$path = dirname(__DIR__, 5) . self::DATA_PATH;
		$contents = file_get_contents($path);
		self::assertIsString($contents);
		$decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray($decoded);

		self::$raw = [];
		foreach($decoded as $identifier => $entry){
			self::assertIsString($identifier);
			self::assertIsArray($entry);
			$spawnRules = $entry["minecraft:spawn_rules"] ?? null;
			self::assertIsArray($spawnRules);
			$categoryId = $spawnRules["description"]["population_control"] ?? null;
			self::assertIsString($categoryId, "$identifier has no population_control");
			$conditions = $spawnRules["conditions"] ?? [];
			self::assertIsArray($conditions);
			$groups = [];
			foreach(array_is_list($conditions) ? $conditions : [$conditions] as $group){
				self::assertIsArray($group);
				$groups[] = $group;
			}
			self::$raw[$identifier] = [$categoryId, $groups];
		}

		// Throws on anything the strict loader can't compile.
		self::$parsed = SpawnRulesParser::createVanilla(new BiomeTagMap([]))->parseFile($path);
	}

	public function testOnlyEventDrivenPopulationsAreSkipped() : void{
		$skipped = array_keys(array_diff_key(self::$raw, self::$parsed));
		$expected = [];
		foreach(self::$raw as $identifier => [$categoryId]){
			if(isset(self::SKIPPED_CATEGORIES[$categoryId])){
				$expected[] = $identifier;
			}
		}
		sort($skipped);
		sort($expected);

		self::assertNotEmpty($expected, "the data no longer has event-driven populations; update SKIPPED_CATEGORIES");
		self::assertSame($expected, $skipped);
	}

	/**
	 * A parsed entry keeps every raw group except those using an unsupported component
	 * (today only in skipped entries) and those with no habitat marker, which spawn
	 * nowhere in vanilla either (guardian).
	 */
	public function testOnlyGroupsWithUnsupportedComponentsOrNoHabitatAreDropped() : void{
		$unsupported = [];
		foreach(SpawnRulesParser::UNSUPPORTED_VANILLA as $component){
			$unsupported[$component] = true;
		}

		foreach(self::$parsed as $identifier => [, $groups]){
			$kept = 0;
			foreach(self::$raw[$identifier][1] as $group){
				if(!self::usesAny($group, $unsupported) && self::usesAny($group, self::HABITAT_MARKERS)){
					$kept++;
				}
			}
			self::assertCount($kept, $groups, $identifier);
		}
	}

	/**
	 * registerVanilla() throws at server start for a rule whose category isn't registered.
	 */
	public function testEveryCategoryIsRegistered() : void{
		foreach(self::$parsed as $identifier => [$categoryId]){
			self::assertSame(self::$raw[$identifier][0], $categoryId, $identifier);
			self::assertTrue(MobCategoryRegistry::getInstance()->has($categoryId), "$identifier uses unregistered category \"$categoryId\"");
		}
	}

	public function testGroupWithUnsupportedComponentIsDropped() : void{
		self::assertCount(1, self::parseConditions(<<<'JSON'
			{"minecraft:spawns_on_surface": {}, "minecraft:delay_filter": {"min": 1, "max": 2, "identifier": "x", "spawn_chance": 50}},
			{"minecraft:spawns_on_surface": {}}
			JSON));
	}

	public function testLiquidMarkersSetTheGroupLiquid() : void{
		$groups = self::parseConditions(<<<'JSON'
			{"minecraft:spawns_on_surface": {}, "minecraft:spawns_underwater": {}},
			{"minecraft:spawns_on_surface": {}, "minecraft:spawns_lava": {}},
			{"minecraft:spawns_on_surface": {}}
			JSON);

		self::assertSame(
			[SpawnLiquid::WATER, SpawnLiquid::LAVA, SpawnLiquid::NONE],
			array_map(static fn(SpawnRuleGroup $group) : SpawnLiquid => $group->getRequiredLiquid(), $groups)
		);
	}

	public function testDistanceFilterReplacesTheVanillaDefault() : void{
		$groups = self::parseConditions(<<<'JSON'
			{"minecraft:spawns_on_surface": {}, "minecraft:spawns_underground": {}},
			{"minecraft:spawns_on_surface": {}, "minecraft:spawns_underground": {}, "minecraft:distance_filter": {"min": 12, "max": 32}}
			JSON);

		self::assertSame([24.0, 128.0], [$groups[0]->getMinPlayerDistance(), $groups[0]->getMaxPlayerDistance()]);
		self::assertSame([12.0, 32.0], [$groups[1]->getMinPlayerDistance(), $groups[1]->getMaxPlayerDistance()]);
	}

	/**
	 * Parses one entry whose "conditions" list holds the given comma-separated groups.
	 *
	 * @phpstan-return list<SpawnRuleGroup>
	 */
	private static function parseConditions(string $groups) : array{
		$json = <<<JSON
		{
			"minecraft:test": {
				"format_version": "1.8.0",
				"minecraft:spawn_rules": {
					"description": {"identifier": "minecraft:test", "population_control": "monster"},
					"conditions": [$groups]
				}
			}
		}
		JSON;

		return SpawnRulesParser::createVanilla(new BiomeTagMap([]))->parse($json)["minecraft:test"][1];
	}

	/**
	 * @phpstan-param array<string, mixed> $group
	 * @phpstan-param array<string, true>  $components unprefixed component names
	 */
	private static function usesAny(array $group, array $components) : bool{
		foreach(array_map(static fn(int|string $key) : string => (string) $key, array_keys($group)) as $key){
			$name = str_starts_with($key, "minecraft:") ? substr($key, strlen("minecraft:")) : $key;
			if(isset($components[$name])){
				return true;
			}
		}

		return false;
	}
}
