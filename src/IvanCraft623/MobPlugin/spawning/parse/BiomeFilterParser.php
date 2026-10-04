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
use IvanCraft623\MobPlugin\spawning\condition\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\condition\Not;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaBiomeFilterKeys;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaBiomeFilterOperators;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaBiomeFilterTestNames;

use function array_keys;
use function array_map;
use function count;
use function get_debug_type;
use function implode;
use function is_bool;

/**
 * Compiles a biome_filter value into a SpawnCondition tree; unknown tests, operators or
 * value types throw SpawnRulesParseException with the JSON path.
 */
final class BiomeFilterParser{
	private const ALL = 0;
	private const ANY = 1;
	private const NONE = 2;

	/** Every group key the filter schema declares, aliases included. */
	private const GROUPS = [
		VanillaBiomeFilterKeys::GROUP_ALL_OF => self::ALL,
		VanillaBiomeFilterKeys::GROUP_ALL => self::ALL,
		VanillaBiomeFilterKeys::GROUP_AND => self::ALL,
		VanillaBiomeFilterKeys::GROUP_ANY_OF => self::ANY,
		VanillaBiomeFilterKeys::GROUP_ANY => self::ANY,
		VanillaBiomeFilterKeys::GROUP_OR => self::ANY,
		VanillaBiomeFilterKeys::GROUP_NONE_OF => self::NONE,
		VanillaBiomeFilterKeys::GROUP_NOT => self::NONE,
	];

	/**
	 * The fields the schema declares for a test. Subject and domain say what an entity
	 * filter is tested on and mean nothing for a biome: they are accepted and ignored.
	 */
	private const FIELDS = [
		VanillaBiomeFilterKeys::FIELD_TEST => true,
		VanillaBiomeFilterKeys::FIELD_OPERATOR => true,
		VanillaBiomeFilterKeys::FIELD_VALUE => true,
		VanillaBiomeFilterKeys::FIELD_SUBJECT => true,
		VanillaBiomeFilterKeys::FIELD_DOMAIN => true,
	];

	/** The biome tag is_snow_covered is approximated by. */
	private const SNOW_COVERED_BIOME_TAG = "frozen";

	/** Operator => whether it negates the test. */
	private const OPERATORS = [
		VanillaBiomeFilterOperators::EQUALS => false,
		VanillaBiomeFilterOperators::NOT_EQUALS => true,
		VanillaBiomeFilterOperators::NOT => true,
	];

	/** @phpstan-var array<string, true> */
	private array $unknownTags = [];

	public function __construct(
		private readonly BiomeTagMap $tags
	){}

	/**
	 * Tags tested so far that no biome carries. Not an error: vanilla references tags
	 * of biomes the bundled bedrock-data doesn't have yet.
	 *
	 * @phpstan-return list<string>
	 */
	public function getUnknownTags() : array{
		return array_keys($this->unknownTags);
	}

	/**
	 * A single filter node, or a bare list of nodes (AND shorthand).
	 *
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function parse(ComponentParseContext $ctx) : SpawnCondition{
		$nodes = array_map($this->fromNode(...), $ctx->objectOrList());

		return match(count($nodes)){
			0 => throw new SpawnRulesParseException("'{$ctx->getPath()}' must not be empty"),
			1 => $nodes[0],
			default => new AllOf($nodes),
		};
	}

	/**
	 * @phpstan-throws SpawnRulesParseException
	 */
	private function fromNode(SpawnData $node) : SpawnCondition{
		$hasTest = $node->has(VanillaBiomeFilterKeys::FIELD_TEST);
		foreach($node->keys() as $key){
			// A key read as nothing would change what the filter matches without a word.
			if(!isset(self::FIELDS[$key]) && !isset(self::GROUPS[$key])){
				$known = implode(", ", [...array_keys(self::FIELDS), ...array_keys(self::GROUPS)]);
				throw new SpawnRulesParseException("'{$node->at($key)}' is not a filter key; the schema declares $known");
			}
			if(!$hasTest && isset(self::FIELDS[$key])){
				throw new SpawnRulesParseException("'{$node->at($key)}' needs a test in the same node");
			}
		}
		$conditions = [];
		if($hasTest){
			$conditions[] = $this->fromLeaf($node);
		}
		foreach(self::GROUPS as $group => $kind){
			if(!$node->has($group)){
				continue;
			}
			$children = array_map($this->fromNode(...), $node->objectOrList($group));
			if(count($children) === 0){
				throw new SpawnRulesParseException("'{$node->at($group)}' must not be empty");
			}
			$conditions[] = match($kind){
				self::ALL => new AllOf($children),
				self::ANY => new AnyOf($children),
				self::NONE => new AllOf(array_map(static fn(SpawnCondition $c) => new Not($c), $children)),
			};
		}

		return match(count($conditions)){
			0 => throw new SpawnRulesParseException("'{$node->path}' must have a test or a group"),
			1 => $conditions[0],
			default => new AllOf($conditions),
		};
	}

	/**
	 * @phpstan-throws SpawnRulesParseException
	 */
	private function fromLeaf(SpawnData $node) : SpawnCondition{
		$operatorPath = $node->at(VanillaBiomeFilterKeys::FIELD_OPERATOR);
		$valuePath = $node->at(VanillaBiomeFilterKeys::FIELD_VALUE);

		// A test without an operator compares with "==".
		$negated = false;
		if($node->has(VanillaBiomeFilterKeys::FIELD_OPERATOR) && $node->raw(VanillaBiomeFilterKeys::FIELD_OPERATOR) !== null){
			$operator = $node->string(VanillaBiomeFilterKeys::FIELD_OPERATOR);
			$negated = self::OPERATORS[$operator]
				?? throw new SpawnRulesParseException("'$operatorPath' must be one of " . implode(", ", array_keys(self::OPERATORS)) . ", got '$operator'");
		}

		$test = $node->string(VanillaBiomeFilterKeys::FIELD_TEST);
		switch($test){
			case VanillaBiomeFilterTestNames::HAS_BIOME_TAG:
				$tag = $node->string(VanillaBiomeFilterKeys::FIELD_VALUE);
				if($tag === ""){
					throw new SpawnRulesParseException("'$valuePath' must not be empty");
				}
				break;
			case VanillaBiomeFilterTestNames::IS_SNOW_COVERED:
				// A boolean test: value defaults to true, and false flips the comparison.
				$expected = $node->has(VanillaBiomeFilterKeys::FIELD_VALUE) ? $node->raw(VanillaBiomeFilterKeys::FIELD_VALUE) : true;
				if(!is_bool($expected)){
					throw new SpawnRulesParseException("'$valuePath' must be a boolean, got " . get_debug_type($expected));
				}
				$tag = self::SNOW_COVERED_BIOME_TAG;
				$negated = $negated === $expected;
				break;
			default:
				throw new SpawnRulesParseException("'{$node->at(VanillaBiomeFilterKeys::FIELD_TEST)}' names a test this loader doesn't implement: '$test'");
		}
		if(!$this->tags->isKnownTag($tag)){
			$this->unknownTags[$tag] = true;
		}
		$condition = new BiomeTagCondition($this->tags, $tag);

		return $negated ? new Not($condition) : $condition;
	}
}
