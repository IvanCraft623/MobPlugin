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

namespace IvanCraft623\MobPlugin\spawning\parse;

use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaBiomeFilterKeys;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use PHPUnit\Framework\TestCase;
use function array_filter;
use function array_keys;
use function array_values;
use function json_encode;
use function str_starts_with;
use const ARRAY_FILTER_USE_KEY;
use const JSON_THROW_ON_ERROR;

/**
 * What the strict loader rejects, tolerates and warns about, on hand-written rules.
 */
final class SpawnRulesParserStrictnessTest extends TestCase{
	private const IDENTIFIER = "minecraft:probe";

	private const FROZEN_BIOME = 1;
	private const WARM_BIOME = 2;
	private const UNTAGGED_BIOME = 3;

	private SpawnRulesParser $parser;

	protected function setUp() : void{
		$this->parser = SpawnRulesParser::createVanilla(new BiomeTagMap([
			self::FROZEN_BIOME => ["frozen" => true],
			self::WARM_BIOME => ["warm" => true],
		]));
	}

	/**
	 * @phpstan-param array<string, mixed>|list<mixed> $conditions
	 * @phpstan-return list<SpawnRuleGroup>
	 */
	private function parseConditions(array $conditions) : array{
		$json = json_encode([self::IDENTIFIER => ["minecraft:spawn_rules" => [
			"description" => ["identifier" => self::IDENTIFIER, "population_control" => "animal"],
			"conditions" => $conditions,
		]]], JSON_THROW_ON_ERROR);

		return $this->parser->parse($json)[self::IDENTIFIER][1];
	}

	/**
	 * @phpstan-param array<string, mixed> $components
	 */
	private function parseGroup(array $components) : SpawnRuleGroup{
		$groups = $this->parseConditions([["minecraft:spawns_on_surface" => []] + $components]);
		self::assertCount(1, $groups);

		return $groups[0];
	}

	/**
	 * @phpstan-return iterable<string, array{array<string, mixed>, string}>
	 */
	public static function rejectedComponents() : iterable{
		yield "misspelled key" => [["minecraft:brightness_filter" => ["mni" => 0, "max" => 7]], "brightness_filter' "];
		yield "text for a number" => [["minecraft:weight" => ["default" => "abc"]], "weight.default"];
		yield "fractional integer" => [["minecraft:herd" => ["min_size" => 2.9, "max_size" => 4]], "herd.min_size"];
		yield "number for a name" => [["minecraft:difficulty_filter" => ["min" => 1]], "difficulty_filter.min"];
		yield "negative weight" => [["minecraft:weight" => ["default" => -5]], "Weight must not be negative"];
		yield "empty herd" => [["minecraft:herd" => []], "herd' must not be empty"];
		yield "empty biome filter" => [["minecraft:biome_filter" => []], "biome_filter' must not be empty"];
		yield "empty any_of" => [["minecraft:biome_filter" => ["any_of" => []]], "biome_filter.any_of' must not be empty"];
		yield "snow test on a non-boolean" => [["minecraft:biome_filter" => ["test" => "is_snow_covered", "value" => "yes"]], "biome_filter.value' must be a boolean"];
		yield "unknown component" => [["minecraft:made_up" => []], "made_up' is not a recognized"];
		yield "empty biome filter node" => [["minecraft:biome_filter" => ["any_of" => [["test" => "has_biome_tag", "value" => "warm"], []]]], "any_of[1]'"];
		yield "unknown filter group" => [["minecraft:biome_filter" => ["one_of" => [["test" => "has_biome_tag", "value" => "warm"]]]], "biome_filter.one_of' is not a filter key"];
		yield "misspelled filter field" => [["minecraft:biome_filter" => ["test" => "has_biome_tag", "opertor" => "!=", "value" => "warm"]], "biome_filter.opertor' is not a filter key"];
		yield "filter field without a test" => [["minecraft:biome_filter" => ["operator" => "!=", "any_of" => [["test" => "has_biome_tag", "value" => "warm"]]]], "biome_filter.operator' needs a test"];
		yield "unknown filter operator" => [["minecraft:biome_filter" => ["test" => "has_biome_tag", "operator" => "<", "value" => "warm"]], "biome_filter.operator' must be one of"];
		yield "unknown filter test" => [["minecraft:biome_filter" => ["test" => "is_humid"]], "biome_filter.test' names a test"];
		yield "two liquids" => [["minecraft:spawns_underwater" => [], "minecraft:spawns_lava" => []], "can't require both"];
	}

	/**
	 * @dataProvider rejectedComponents
	 * @phpstan-param array<string, mixed> $components
	 */
	public function testRejects(array $components, string $pathFragment) : void{
		$this->expectException(SpawnRulesParseException::class);
		$this->expectExceptionMessage($pathFragment);
		$this->parseGroup($components);
	}

	public function testLosslessNumberSpellingsAreAccepted() : void{
		$group = $this->parseGroup([
			"minecraft:weight" => ["default" => 8.0, "rarity" => 2.0],
			"minecraft:herd" => ["min_size" => 2.0, "max_size" => 4.0],
		]);

		self::assertSame(8, $group->getWeight());
		self::assertSame(2, $group->getRarity());
		self::assertSame(2, $group->getHerdMin());
		self::assertSame(4, $group->getHerdMax());
	}

	public function testEmptyConditionsMeanNoGroups() : void{
		self::assertSame([], $this->parseConditions([]));
	}

	/**
	 * @phpstan-return iterable<string, array{array<string, mixed>, bool}>
	 */
	public static function snowTests() : iterable{
		yield "bare" => [[], true];
		yield "== true" => [["operator" => "==", "value" => true], true];
		yield "!=" => [["operator" => "!="], false];
		yield "not" => [["operator" => "not"], false];
		yield "== false" => [["operator" => "==", "value" => false], false];
		yield "!= false" => [["operator" => "!=", "value" => false], true];
	}

	/**
	 * @dataProvider snowTests
	 * @phpstan-param array<string, mixed> $extra
	 */
	public function testSnowCoveredHonoursOperatorAndValue(array $extra, bool $matchesFrozen) : void{
		$group = $this->parseGroup(["minecraft:biome_filter" => ["test" => "is_snow_covered"] + $extra]);

		self::assertSame($matchesFrozen, $group->matches(new StubContext(biomeId: self::FROZEN_BIOME)));
		self::assertSame(!$matchesFrozen, $group->matches(new StubContext(biomeId: self::WARM_BIOME)));
	}

	/**
	 * The filter schema declares aliases for each group. Every declared key compiles to
	 * its meaning, told apart by a biome with one of two tags and a biome with neither.
	 */
	public function testEveryDeclaredFilterGroupKeyIsUnderstood() : void{
		// Key => whether it matches [the warm biome, a biome with no tags].
		$all = [false, false];
		$any = [true, false];
		$none = [false, true];
		$expectations = [
			VanillaBiomeFilterKeys::GROUP_ALL_OF => $all, VanillaBiomeFilterKeys::GROUP_ALL => $all, VanillaBiomeFilterKeys::GROUP_AND => $all,
			VanillaBiomeFilterKeys::GROUP_ANY_OF => $any, VanillaBiomeFilterKeys::GROUP_ANY => $any, VanillaBiomeFilterKeys::GROUP_OR => $any,
			VanillaBiomeFilterKeys::GROUP_NONE_OF => $none, VanillaBiomeFilterKeys::GROUP_NOT => $none,
		];
		$declared = array_filter((new \ReflectionClass(VanillaBiomeFilterKeys::class))->getConstants(), static fn(string $name) : bool => str_starts_with($name, "GROUP_"), ARRAY_FILTER_USE_KEY);
		self::assertEqualsCanonicalizing(array_values($declared), array_keys($expectations), "the schema added or removed a group key");

		foreach($expectations as $key => [$matchesWarm, $matchesUntagged]){
			$group = $this->parseGroup(["minecraft:biome_filter" => [$key => [
				["test" => "has_biome_tag", "value" => "warm"],
				["test" => "has_biome_tag", "value" => "frozen"],
			]]]);
			self::assertSame($matchesWarm, $group->matches(new StubContext(biomeId: self::WARM_BIOME)), "$key in the warm biome");
			self::assertSame($matchesUntagged, $group->matches(new StubContext(biomeId: self::UNTAGGED_BIOME)), "$key in the untagged biome");
		}
	}

	public function testSubjectAndDomainAreAcceptedAndIgnored() : void{
		$group = $this->parseGroup(["minecraft:biome_filter" => ["test" => "has_biome_tag", "subject" => "self", "domain" => "any", "value" => "warm"]]);

		self::assertTrue($group->matches(new StubContext(biomeId: self::WARM_BIOME)));
		self::assertFalse($group->matches(new StubContext(biomeId: self::FROZEN_BIOME)));
	}

	public function testUnknownBiomeTagsAreReportedNotRejected() : void{
		$this->parseGroup(["minecraft:biome_filter" => ["any_of" => [
			["test" => "has_biome_tag", "value" => "warm"],
			["test" => "has_biome_tag", "operator" => "!=", "value" => "nowhere"],
			["test" => "has_biome_tag", "value" => "nowhere"],
		]]]);

		self::assertSame(["nowhere"], $this->parser->getUnknownBiomeTags());
	}
}
