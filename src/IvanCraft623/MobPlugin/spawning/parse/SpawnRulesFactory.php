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

use IvanCraft623\MobPlugin\entity\MobCategory;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\ChainBlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\StringToItemBlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\VanillaAliasBlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use pocketmine\utils\Filesystem;
use function array_is_list;
use function count;
use function is_array;
use function is_string;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Parses the compiled spawn-rules resource into SpawnRules through the
 * SpawnConditionRegistry.
 *
 * The component inventory and the document envelope keys (conditions, description,
 * population_control) are generated from the official Mojang spawn schemas
 * (SpawnComponent, SpawnSchema) — see tools/spawn-rules/generate-schema.php. Only the
 * outer resource wrapper ("minecraft:spawn_rules" and "format_version") is part of the
 * spawn-rule *file* shape rather than the enumerated schema, so it stays a named
 * constant here.
 *
 * The loader is strict — it has no warning channel: any value it cannot compile aborts
 * the whole load with SpawnModelParseException (SpawnParseException details carry the
 * offending JSON path). Exactly two degradations exist, both first-class documented
 * policy rather than fallback behavior: NON_NATURAL_POPULATION_CONTROL entries are
 * skipped (vanilla spawns them through events, not natural spawning) and
 * KNOWN_MISSING_BLOCKS names are dropped from block filters (PocketMine-MP has no
 * equivalent block).
 */
final class SpawnRulesFactory{
	private const COMPONENT_PREFIX = "minecraft:";

	/** Outer key of the whole spawn-rule document. Not enumerated by the schema. */
	private const SPAWN_RULES_KEY = "minecraft:spawn_rules";

	/** Top-level version marker of a spawn-rule document. Not enumerated by the schema. */
	private const FORMAT_VERSION = "format_version";

	/**
	 * Vanilla population_control values with no MobCategory case: Bedrock spawns them
	 * through events (patrols/raids), not natural spawning, so their rule sets are
	 * skipped by design. Any other unknown value aborts the load.
	 *
	 * @var array<string, true>
	 */
	private const NON_NATURAL_POPULATION_CONTROL = ["pillager" => true, "pillager_patrol" => true];

	/**
	 * Vanilla block names the block resolver can never resolve (PocketMine-MP has no
	 * equivalent block); dropped from block filters by design — the filter keeps its
	 * resolvable names. Any other unresolvable name aborts the load.
	 *
	 * @var array<string, true>
	 */
	private const KNOWN_MISSING_BLOCKS = ["minecraft:powder_snow" => true];

	public function __construct(
		private SpawnConditionRegistry $components,
		private BlockNameResolver $blockResolver
	){}

	public static function createDefault() : self{
		return new self(
			SpawnConditionRegistry::getInstance(),
			new ChainBlockNameResolver([
				new StringToItemBlockNameResolver(),
				new VanillaAliasBlockNameResolver(),
			])
		);
	}

	public function getConditionRegistry() : SpawnConditionRegistry{
		return $this->components;
	}

	/**
	 * @phpstan-return array<string, SpawnRules>
	 * @phpstan-throws SpawnModelParseException on malformed JSON or any value the loader cannot compile
	 */
	public function loadFile(string $path) : array{
		return $this->loadJson(Filesystem::fileGetContents($path));
	}

	/**
	 * @phpstan-return array<string, SpawnRules>
	 * @phpstan-throws SpawnModelParseException on malformed JSON or any value the loader cannot compile
	 */
	public function loadJson(string $json) : array{
		try{
			$decoded = SpawnData::fromJson($json);
		}catch(SpawnParseException $e){
			throw new SpawnModelParseException("Spawn rules resource is not valid JSON: " . $e->getMessage(), 0, $e);
		}

		$entries = [];
		foreach($decoded->keys() as $identifier){
			if($identifier === ""){
				throw new SpawnModelParseException("Spawn rules: invalid empty entry key.");
			}
			try{
				$rules = $this->parseEntry($decoded->object($identifier), $identifier);
			}catch(SpawnModelParseException $e){
				throw $e;
			}catch(\InvalidArgumentException $e){
				// SpawnParseException (bad value shape, carries the JSON path) and
				// condition-constructor validation errors (e.g. an unknown difficulty
				// name) both abort the load, prefixed with the entry's identifier.
				throw new SpawnModelParseException("Spawn rules for \"$identifier\": " . $e->getMessage(), 0, $e);
			}
			if($rules !== null){
				$entries[$identifier] = $rules;
			}
		}

		return $entries;
	}

	/** Parses one entry: population_control → MobCategory, then its condition objects.
	 *
	 * Returns null only for by-design skipped entries (NON_NATURAL_POPULATION_CONTROL).
	 *
	 * @phpstan-throws \InvalidArgumentException when the entry cannot be compiled
	 */
	private function parseEntry(SpawnData $body, string $identifier) : ?SpawnRules{
		$spawnRules = $body->objectNullable(self::SPAWN_RULES_KEY);
		if($spawnRules === null){
			throw new SpawnModelParseException("Spawn rules for \"$identifier\": missing \"" . self::SPAWN_RULES_KEY . "\" object.");
		}

		$description = $spawnRules->objectNullable(SpawnSchema::KEY_DESCRIPTION);
		$populationControl = $description !== null && $description->has(SpawnSchema::KEY_POPULATION_CONTROL)
			? $description->stringNullable(SpawnSchema::KEY_POPULATION_CONTROL)
			: null;
		if(!is_string($populationControl) || $populationControl === ""){
			throw new SpawnModelParseException("Spawn rules for \"$identifier\": missing \"" . SpawnSchema::KEY_DESCRIPTION . "." . SpawnSchema::KEY_POPULATION_CONTROL . "\".");
		}

		if($body->has(self::FORMAT_VERSION)){
			SpawnSchemaVersion::assertSupported($body->string(self::FORMAT_VERSION), $identifier);
		}

		$category = MobCategory::tryFrom($populationControl);
		if($category === null){
			if(isset(self::NON_NATURAL_POPULATION_CONTROL[$populationControl])){
				return null; // event-driven in vanilla (patrols/raids); rule set skipped by design
			}
			throw new SpawnModelParseException("Spawn rules for \"$identifier\": unknown population_control \"$populationControl\".");
		}

		$groups = [];
		if($spawnRules->has(SpawnSchema::KEY_CONDITIONS)){
			$groups = $this->parseConditions($spawnRules, $identifier);
		} // else: legitimate vanilla data — mobs without natural spawns (e.g. blaze) load with no conditions.

		return new SpawnRules($identifier, $category, $groups);
	}

	/**
	 * @phpstan-return list<SpawnConditionGroup>
	 * @phpstan-throws SpawnParseException when a condition cannot be compiled
	 */
	private function parseConditions(SpawnData $spawnRules, string $identifier) : array{
		$conditions = $spawnRules->objectOrList(SpawnSchema::KEY_CONDITIONS);
		$groups = [];
		foreach($conditions as $conditionData){
			$groups[] = $this->parseCondition($conditionData, $identifier);
		}

		return $groups;
	}

	/** Compiles one condition object; any unusable value aborts the load.
	 *
	 * @phpstan-throws SpawnParseException when the condition cannot be compiled
	 */
	private function parseCondition(SpawnData $condition, string $identifier) : SpawnConditionGroup{
		// Normalize "minecraft:"-prefixed keys once; the registry is keyed without prefix.
		$normalized = [];
		foreach($condition->keys() as $rawKey){
			$normalized[self::normalizeComponent($rawKey)] = $condition->raw($rawKey);
		}
		$condition = new SpawnData($normalized, $condition->path);

		if(count($condition->keys()) === 0){
			throw new SpawnParseException("'{$condition->path}' has no spawn-rule components");
		}

		$builder = new SpawnGroupBuilder($identifier);
		foreach($condition->keys() as $key){
			$parser = $this->components->get($key);
			if($parser === null){
				throw new SpawnParseException("'{$condition->at($key)}' is not a recognized spawn-rule component");
			}
			$parser($condition, $key, $builder, $this);
		}

		return $builder->build();
	}

	private static function normalizeComponent(string $key) : string{
		return str_starts_with($key, self::COMPONENT_PREFIX) ? substr($key, strlen(self::COMPONENT_PREFIX)) : $key;
	}

	/**
	 * Resolves a block-name filter to a set of block type ids. KNOWN_MISSING_BLOCKS names
	 * are dropped by design; any other unresolvable name aborts the load.
	 *
	 * @phpstan-return array<int, true>
	 * @phpstan-throws SpawnParseException when a block name cannot be resolved
	 */
	public function resolveBlockSet(SpawnData $condition, string $component) : array{
		$set = [];
		foreach($this->readBlockNames($condition, $component) as $name){
			if(isset(self::KNOWN_MISSING_BLOCKS[$name])){
				continue;
			}
			$typeId = $this->blockResolver->resolve($name);
			if($typeId === null){
				throw new SpawnParseException("'{$condition->at($component)}' block name \"$name\" cannot be resolved");
			}
			$set[$typeId] = true;
		}

		return $set;
	}

	/**
	 * @phpstan-return list<string>
	 */
	private function readBlockNames(SpawnData $condition, string $component) : array{
		$value = $condition->raw($component);
		if(is_string($value)){
			return [$value];
		}
		if(!is_array($value) || !array_is_list($value)){
			throw new SpawnParseException("'{$condition->at($component)}' must be a string or a list of block names");
		}
		$names = [];
		foreach($value as $index => $item){
			if(is_string($item)){
				$names[] = $item;
				continue;
			}
			if(is_array($item) && isset($item["name"]) && is_string($item["name"])){
				$names[] = $item["name"];
				continue;
			}
			throw new SpawnParseException("'{$condition->at($component)}' entries must be strings or {name: string} objects");
		}

		return $names;
	}
}
