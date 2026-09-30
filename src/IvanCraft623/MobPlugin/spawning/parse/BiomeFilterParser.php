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

use function array_map;
use function count;

/**
 * Compiles a biome_filter value into a SpawnCondition tree; unknown tests, operators or
 * value types throw SpawnRulesParseException with the JSON path.
 */
final class BiomeFilterParser{
	public function __construct(
		private readonly BiomeTagMap $tags
	){}

	/**
	 * A single filter node, or a bare list of nodes (AND shorthand).
	 *
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function parse(ComponentParseContext $ctx) : SpawnCondition{
		$nodes = array_map($this->fromNode(...), $ctx->objectOrList());

		return count($nodes) === 1 ? $nodes[0] : new AllOf($nodes);
	}

	/**
	 * @phpstan-throws SpawnRulesParseException
	 */
	private function fromNode(SpawnData $node) : SpawnCondition{
		$conditions = [];
		if($node->has("test")){
			$conditions[] = $this->fromLeaf($node);
		}
		foreach(["all_of", "any_of", "none_of"] as $group){
			if(!$node->has($group)){
				continue;
			}
			$children = array_map($this->fromNode(...), $node->objectOrList($group));
			$conditions[] = match($group){
				"all_of" => new AllOf($children),
				"any_of" => new AnyOf($children),
				"none_of" => new AllOf(array_map(static fn(SpawnCondition $c) => new Not($c), $children)),
			};
		}

		return match(count($conditions)){
			0 => throw new SpawnRulesParseException("'{$node->path}' must have a test, all_of, any_of or none_of"),
			1 => $conditions[0],
			default => new AllOf($conditions),
		};
	}

	/**
	 * @phpstan-throws SpawnRulesParseException
	 */
	private function fromLeaf(SpawnData $node) : SpawnCondition{
		$test = $node->string("test");
		if($test === "is_snow_covered"){
			return new BiomeTagCondition($this->tags, "frozen");
		}
		if($test !== "has_biome_tag"){
			throw new SpawnRulesParseException("'{$node->at("test")}' must be 'has_biome_tag' or 'is_snow_covered', got '$test'");
		}

		$value = $node->string("value");
		if($value === ""){
			throw new SpawnRulesParseException("'{$node->at("value")}' must not be empty");
		}
		$operator = null;
		if($node->has("operator") && $node->raw("operator") !== null){
			$operator = $node->string("operator");
		}
		$condition = new BiomeTagCondition($this->tags, $value);

		return match($operator){
			null, "==" => $condition,
			"!=", "not" => new Not($condition),
			default => throw new SpawnRulesParseException("'{$node->at("operator")}' must be one of '==', '!=', 'not', got '$operator'"),
		};
	}
}
