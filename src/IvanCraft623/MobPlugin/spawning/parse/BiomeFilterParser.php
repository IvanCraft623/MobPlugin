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
use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\AnyOf;
use IvanCraft623\MobPlugin\spawning\condition\Not;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\BiomeTagCondition;

use function array_is_list;
use function array_keys;
use function array_map;
use function count;
use function get_debug_type;
use function implode;
use function is_array;
use function is_string;

/**
 * Compiles a biome_filter value into a SpawnCondition tree; unknown tests, operators or
 * value types throw SpawnRulesParseException with the JSON path.
 */
final class BiomeFilterParser{
	/** @phpstan-var array<string, true> */
	private const KNOWN_TESTS = ["has_biome_tag" => true, "is_snow_covered" => true];

	public function __construct(
		private readonly BiomeTagMap $tags
	){}

	/**
	 * Parses the biome_filter component scoped to one component occurrence: a single node
	 * or a bare list (AND shorthand). Reconstructs the structural tree from the raw value,
	 * so no raw SpawnData ever reaches the parser closure.
	 *
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function parse(ComponentParseContext $ctx) : SpawnCondition{
		$value = $ctx->getValue();
		if(!is_array($value)){
			throw new SpawnRulesParseException("'{$ctx->getPath()}' must be an object or a list of objects");
		}
		if(!$this->isAssoc($value)){
			$nodes = [];
			foreach($value as $index => $entry){
				if(!is_array($entry) || array_is_list($entry)){
					throw new SpawnRulesParseException("'{$ctx->getPath()}[{$index}]' must be an object, got " . get_debug_type($entry));
				}
				$nodes[] = new SpawnData($entry, "{$ctx->getPath()}[{$index}]");
			}

			return new AllOf(array_map($this->fromNode(...), $nodes));
		}

		return $this->fromNode(new SpawnData($value, $ctx->getPath()));
	}

	/**
	 * @phpstan-throws SpawnRulesParseException
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
	 * @phpstan-throws SpawnRulesParseException
	 */
	private function fromLeaf(SpawnData $node) : SpawnCondition{
		$test = $node->string("test");
		if(!isset(self::KNOWN_TESTS[$test])){
			throw new SpawnRulesParseException("'{$node->at("test")}' must be one of " . $this->quoteList(self::KNOWN_TESTS) . ", got '$test'");
		}
		if($test === "is_snow_covered"){
			return new BiomeTagCondition($this->tags, ["frozen"], []);
		}

		$value = $node->raw("value");
		if(!is_string($value) || $value === ""){
			throw new SpawnRulesParseException("'{$node->at("value")}' must be a non-empty string");
		}
		$operator = null;
		if($node->has("operator") && $node->raw("operator") !== null){
			$operator = $node->string("operator");
		}
		$match = match($operator){
			null, "==" => true,
			"!=", "not" => false,
			default => throw new SpawnRulesParseException("'{$node->at("operator")}' must be one of '==', '!=', 'not', got '$operator'"),
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
