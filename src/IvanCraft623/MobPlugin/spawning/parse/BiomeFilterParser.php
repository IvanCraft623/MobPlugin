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

use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\AnyOf;
use IvanCraft623\MobPlugin\spawning\condition\Not;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;

use function array_is_list;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_array;
use function is_string;

/**
 * Compiles a biome_filter value into a SpawnCondition tree: leaves become
 * BiomeTagCondition,
 * all_of/any_of/none_of (and the bare array form, which is all_of) become the AllOf /
 * AnyOf / Not combinators, nested to arbitrary depth. Unknown tests, operators or value
 * types throw SpawnParseException with the JSON path.
 */
final class BiomeFilterParser{
	/** @phpstan-var array<string, true> */
	private const KNOWN_TESTS = ["has_biome_tag" => true, "is_snow_covered" => true];

	public function __construct(
		private BiomeTagResolver $tags
	){}

	/**
	 * Parses a "biome_filter" value: a single node or a bare list (AND shorthand).
	 *
	 * @phpstan-throws SpawnParseException
	 */
	public function fromCondition(SpawnData $condition, string $key) : SpawnCondition{
		$value = $condition->raw($key);
		if(!is_array($value)){
			throw new SpawnParseException("'{$condition->at($key)}' must be an object or a list of objects");
		}
		if(!$this->isAssoc($value)){
			$nodes = $condition->objectOrList($key);

			return new AllOf(array_map($this->fromNode(...), $nodes));
		}

		return $this->fromNode($condition->object($key));
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	private function fromNode(SpawnData $node) : SpawnCondition{
		$tests = [];
		if($node->has("test")){
			$tests[] = $this->fromLeaf($node);
		}

		$children = [];
		foreach(["all_of", "any_of", "none_of"] as $group){
			if(!$node->has($group)){
				continue;
			}
			$nodes = $node->objectOrList($group);
			$parsed = array_map($this->fromNode(...), $nodes);
			$children[] = match($group){
				"all_of" => new AllOf($parsed),
				"any_of" => new AnyOf($parsed),
				"none_of" => new AllOf(array_map(static fn(SpawnCondition $c) => new Not($c), $parsed)),
			};
		}

		if(count($children) === 0){
			return count($tests) === 1 ? $tests[0] : new AllOf($tests);
		}

		return new AllOf([...$tests, ...$children]);
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	private function fromLeaf(SpawnData $node) : SpawnCondition{
		$test = $node->string("test");
		if(!isset(self::KNOWN_TESTS[$test])){
			throw new SpawnParseException("'{$node->at("test")}' must be one of " . $this->quoteList(self::KNOWN_TESTS) . ", got '$test'");
		}
		if($test === "is_snow_covered"){
			return new BiomeTagCondition($this->tags, ["frozen"], []);
		}

		$value = $node->raw("value");
		if(!is_string($value) || $value === ""){
			throw new SpawnParseException("'{$node->at("value")}' must be a non-empty string");
		}
		$operator = null;
		if($node->has("operator") && $node->raw("operator") !== null){
			$operator = $node->string("operator");
		}
		$match = match($operator){
			null, "==" => true,
			"!=", "not" => false,
			default => throw new SpawnParseException("'{$node->at("operator")}' must be one of '==', '!=', 'not', got '$operator'"),
		};

		return new BiomeTagCondition($this->tags, $match ? [$value] : [], $match ? [] : [$value]);
	}

	/**
	 * @phpstan-param array<string, true> $values
	 */
	private function quoteList(array $values) : string{
		return implode(", ", array_map(static fn(string $v) : string => "'$v'", array_keys($values)));
	}

	/**
	 * Whether a raw decoded value is a JSON object (as opposed to a JSON array).
	 *
	 * @phpstan-param array<array-key, mixed> $value
	 */
	private function isAssoc(array $value) : bool{
		return !array_is_list($value);
	}
}
