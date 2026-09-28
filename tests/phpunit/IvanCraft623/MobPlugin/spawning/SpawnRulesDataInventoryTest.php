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

use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaSpawnConditions;
use PHPUnit\Framework\TestCase;
use function array_is_list;
use function array_keys;
use function dirname;
use function file_get_contents;
use function get_debug_type;
use function get_object_vars;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;
use function strlen;
use function substr;
use const JSON_THROW_ON_ERROR;

require_once dirname(__DIR__, 5) . "/tools/spawn-rules/SpawnRuleSchemaValidator.php";

/**
 * Validates the bundled spawn-rules resource offline against the committed Mojang schemas:
 * every component key is a declared VanillaSpawnConditions constant, and every entry's
 * body validates against the official spawn-rules schema.
 */
final class SpawnRulesDataInventoryTest extends TestCase{
	private const DATA_PATH = "/resources/spawning/spawn_rules.json";
	private const SCHEMA_ROOT = "/vendor/mojang/bedrock-samples/metadata/json_schemas/server/spawn/" . SpawnSchema::SCHEMA_VERSION . "/Spawn Rules.json";

	private ?SpawnRuleSchemaValidator $validator = null;

	private static function repoRoot() : string{
		return dirname(__DIR__, 5);
	}

	public function testBundledResourceIsValidJson() : void{
		self::assertNotSame([], $this->decodeDataAsMap(), "spawn_rules.json must not be empty");
	}

	public function testEveryConditionComponentIsKnown() : void{
		$decoded = $this->decodeDataAsMap();

		$known = [];
		foreach(VanillaSpawnConditions::getAll() as $component){
			$known[$component] = true;
		}

		$unknown = [];
		foreach($decoded as $entry){
			if(!is_array($entry)){
				continue;
			}
			$spawnRules = $entry["minecraft:spawn_rules"] ?? null;
			if(!is_array($spawnRules)){
				continue;
			}
			$conditions = $spawnRules["conditions"] ?? [];
			if(!is_array($conditions)){
				continue;
			}
			foreach(array_is_list($conditions) ? $conditions : [$conditions] as $condition){
				if(!is_array($condition)){
					continue;
				}
				foreach($condition as $key => $_){
					if(!is_string($key)){
						continue;
					}
					$component = str_starts_with($key, "minecraft:") ? substr($key, strlen("minecraft:")) : $key;
					if(!isset($known[$component])){
						$unknown[$key] = true;
					}
				}
			}
		}

		self::assertSame([], $unknown, "spawn_rules.json uses unrecognized condition components: " . implode(", ", array_keys($unknown)));
	}

	public function testEveryEntryValidatesAgainstOfficialSchema() : void{
		$decoded = json_decode($this->readData(), false, 512, JSON_THROW_ON_ERROR);
		self::assertInstanceOf(\stdClass::class, $decoded);
		$entries = get_object_vars($decoded);
		self::assertNotSame([], $entries);

		$validator = $this->getValidator();
		$allErrors = [];
		foreach($entries as $identifier => $body){
			if(!$body instanceof \stdClass){
				$allErrors[$identifier] = ["entry is not an object"];
				continue;
			}
			$spawnRules = $body->{"minecraft:spawn_rules"} ?? null;
			if(!$spawnRules instanceof \stdClass){
				$allErrors[$identifier] = ["missing minecraft:spawn_rules object"];
				continue;
			}
			$errors = $validator->validate($spawnRules);
			if($errors !== []){
				$allErrors[$identifier] = $errors;
			}
		}

		self::assertSame([], $allErrors, "spawn_rules.json entries fail the official spawn schema:\n" . $this->formatErrors($allErrors));
	}

	/**
	 * @param array<string, list<string>> $errors
	 */
	private function formatErrors(array $errors) : string{
		$lines = [];
		foreach($errors as $identifier => $list){
			foreach($list as $error){
				$lines[] = "  $identifier: $error";
			}
		}

		return implode("\n", $lines);
	}

	private function readData() : string{
		$raw = file_get_contents(self::repoRoot() . self::DATA_PATH);
		self::assertIsString($raw, "could not read resources/spawning/spawn_rules.json");

		return $raw;
	}

	/**
	 * Decodes the resource as a PHP array (assoc). Keys are string entity identifiers.
	 *
	 * @phpstan-return array<mixed, mixed>
	 */
	private function decodeDataAsMap() : array{
		$decoded = json_decode($this->readData(), true, 512, JSON_THROW_ON_ERROR);
		if(!is_array($decoded)){
			self::fail("spawn_rules.json must decode to a JSON object; got: " . get_debug_type($decoded));
		}

		return $decoded;
	}

	private function getValidator() : SpawnRuleSchemaValidator{
		return $this->validator ??= SpawnRuleSchemaValidator::fromSchemaTree(
			self::repoRoot() . self::SCHEMA_ROOT,
			SpawnSchema::SCHEMA_VERSION
		);
	}
}
