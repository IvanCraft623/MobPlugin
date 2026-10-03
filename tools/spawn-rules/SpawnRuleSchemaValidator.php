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

namespace IvanCraft623\MobPlugin\tools\spawnrules;

use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use function array_pop;
use function array_slice;
use function dirname;
use function file_get_contents;
use function get_object_vars;
use function is_array;
use function is_string;
use function json_decode;
use function rawurldecode;
use function realpath;
use function sprintf;
use function str_replace;
use const DIRECTORY_SEPARATOR;

/**
 * Validates spawn-rule bodies against the Mojang JSON schemas.
 *
 * The schemas are patched before use: `oneOf` becomes `anyOf` (a strict evaluator can't
 * pick a branch between an object and a list of objects), and relative `$ref`s become
 * absolute file:// URIs.
 */
final class SpawnRuleSchemaValidator{
	private function __construct(
		private readonly Factory $factory,
		private readonly \stdClass $rootSchema
	){}

	/**
	 * Loads and patches every schema reachable from the root one, so the storage never
	 * fetches an unpatched file.
	 *
	 * @throws \RuntimeException when a schema is missing, or the root doesn't declare $schemaVersion
	 */
	public static function fromSchemaTree(string $rootSchemaFile, string $schemaVersion) : self{
		$rootReal = realpath($rootSchemaFile);
		if($rootReal === false){
			throw new \RuntimeException("Spawn Rules.json root schema not found: $rootSchemaFile");
		}

		$storage = new SchemaStorage();
		$loaded = [];
		$pending = [$rootReal];
		while(($path = array_pop($pending)) !== null){
			if(isset($loaded[$path])){
				continue;
			}
			$loaded[$path] = true;
			$schema = json_decode((string) file_get_contents($path));
			if(!$schema instanceof \stdClass){
				throw new \RuntimeException("Schema file is not a JSON object: $path");
			}
			// Every $ref target patchObject() resolves is queued, so the closure is registered.
			$storage->addSchema("file://" . $path, self::patchObject($schema, dirname($path), $pending));
		}

		$rootSchema = $storage->getSchema("file://" . $rootReal);
		if(!$rootSchema instanceof \stdClass){
			throw new \RuntimeException("The spawn-rules root schema did not resolve to an object.");
		}
		$declared = $rootSchema->{"x-format-version"} ?? null;
		if($declared !== $schemaVersion){
			throw new \RuntimeException(sprintf(
				"Spawn Rules.json declares x-format-version \"%s\" but version \"%s\" was requested.",
				is_string($declared) ? $declared : "?",
				$schemaVersion
			));
		}

		return new self(new Factory($storage), $rootSchema);
	}

	/**
	 * Validates one spawn-rules body (the value of "minecraft:spawn_rules").
	 *
	 * @return list<string> human-readable validation errors (empty when valid)
	 */
	public function validate(object $spawnRules) : array{
		$validator = new Validator($this->factory);
		$validator->validate($spawnRules, $this->rootSchema);
		if($validator->isValid()){
			return [];
		}

		$errors = [];
		foreach(array_slice($validator->getErrors(), 0, 10) as $error){
			$errors[] = sprintf("[%s] %s", $error["property"] ?? "?", $error["message"] ?? "?");
		}

		return $errors;
	}

	/**
	 * oneOf -> anyOf, and relative $refs -> absolute file:// URIs (recursively). Every
	 * resolved $ref target is appended to $refs.
	 *
	 * @param list<string> $refs
	 */
	private static function patchObject(\stdClass $node, string $baseDir, array &$refs) : \stdClass{
		$out = new \stdClass();
		foreach(get_object_vars($node) as $key => $value){
			if($key === "oneOf"){
				$out->anyOf = self::patchValue($value, $baseDir, $refs);
			}elseif($key === "\$ref" && is_string($value)){
				$real = realpath(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $baseDir . "/" . rawurldecode($value)));
				if($real === false){
					throw new \RuntimeException("Cannot resolve schema ref \"$value\" from \"$baseDir\"");
				}
				$refs[] = $real;
				$out->{"\$ref"} = "file://" . $real;
			}else{
				$out->{$key} = self::patchValue($value, $baseDir, $refs);
			}
		}

		return $out;
	}

	/**
	 * @param list<string> $refs
	 */
	private static function patchValue(mixed $value, string $baseDir, array &$refs) : mixed{
		if($value instanceof \stdClass){
			return self::patchObject($value, $baseDir, $refs);
		}
		if(is_array($value)){
			foreach($value as $index => $item){
				$value[$index] = self::patchValue($item, $baseDir, $refs);
			}
		}

		return $value;
	}
}
