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

use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\ChainBlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\StringToItemBlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\VanillaAliasBlockNameResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\VanillaBiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use pocketmine\utils\Filesystem;
use function count;
use function is_string;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Parses the compiled spawn-rules resource into SpawnRules. Strict: any value it cannot
 * compile aborts (SpawnModelParseException); only the two documented degradations skip.
 */
final class SpawnRulesFactory{
	private const CONDITION_PREFIX = "minecraft:";

	/** Outer key of the whole spawn-rule document. Not enumerated by the schema. */
	private const SPAWN_RULES_KEY = "minecraft:spawn_rules";

	/**
	 * Vanilla population_control values with no registered natural-spawn category:
	 * Bedrock spawns them through events (patrols/raids), not natural spawning, so their
	 * rule sets are skipped by design. Resolution to a registered MobCategory happens
	 * later at SpawnRuleRegistry::register, so plugin consumers may add custom categories.
	 *
	 * @var array<string, true>
	 */
	private const NON_NATURAL_POPULATION_CONTROL = ["pillager" => true, "pillager_patrol" => true];

	public function __construct(
		private SpawnConditionRegistry $components,
		private BlockNameResolver $blockResolver,
		private BiomeTagResolver $biomeTags
	){}

	public static function createDefault() : self{
		return new self(
			SpawnConditionRegistry::getInstance(),
			new ChainBlockNameResolver([
				new StringToItemBlockNameResolver(),
				new VanillaAliasBlockNameResolver(),
			]),
			new VanillaBiomeTagResolver()
		);
	}

	public function getConditionRegistry() : SpawnConditionRegistry{
		return $this->components;
	}

	public function getBiomeTags() : BiomeTagResolver{
		return $this->biomeTags;
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

	/** Parses one entry: population_control id, then its condition objects.
	 *
	 * Returns null only for by-design skipped entries (NON_NATURAL_POPULATION_CONTROL).
	 * The category id is stored verbatim; it is validated against MobCategoryRegistry only
	 * when the rules are registered (SpawnRuleRegistry::register), allowing consumers to
	 * register custom categories.
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

		if(isset(self::NON_NATURAL_POPULATION_CONTROL[$populationControl])){
			return null; // event-driven in vanilla (patrols/raids); rule set skipped by design
		}

		$groups = [];
		if($spawnRules->has(SpawnSchema::KEY_CONDITIONS)){
			$groups = $this->parseConditions($spawnRules, $identifier);
		} // else: legitimate vanilla data — mobs without natural spawns (e.g. blaze) load with no conditions.

		return new SpawnRules($identifier, $populationControl, $groups);
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
			$normalized[self::normalizeCondition($rawKey)] = $condition->raw($rawKey);
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
			$parser(new SpawnConditionContext($condition, $key, $this->blockResolver, $this->biomeTags), $builder);
		}

		return $builder->build();
	}

	private static function normalizeCondition(string $key) : string{
		return str_starts_with($key, self::CONDITION_PREFIX) ? substr($key, strlen(self::CONDITION_PREFIX)) : $key;
	}
}
