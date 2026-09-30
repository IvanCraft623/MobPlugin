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
use JsonSchema\Uri\Retrievers\FileGetContents;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use function array_map;
use function array_pop;
use function array_slice;
use function dirname;
use function file_get_contents;
use function get_object_vars;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function rawurldecode;
use function realpath;
use function sprintf;
use function str_replace;
use function str_starts_with;
use const DIRECTORY_SEPARATOR;

/**
 * Validates entity spawn-rule bodies against the official Mojang JSON schemas.
 *
 * Mojang ships draft-07 schemas whose unions use `oneOf`; the spawn-rule unions are all
 * "object | list of objects" shapes a strict `oneOf` evaluator cannot always pick a
 * branch for, so unions are patched to `anyOf` before registration. Relative `$ref`s are
 * rewritten to absolute file:// URIs because the schemas' own `$id` paths are not real
 * URLs.
 *
 * Used by the merge tool (tools/spawn-rules/compile.php) as its schema gate.
 */
final class SpawnRuleSchemaValidator{
	private readonly Factory $factory;
	private readonly \stdClass $rootSchema;

	private function __construct(Factory $factory, \stdClass $rootSchema){
		$this->factory = $factory;
		$this->rootSchema = $rootSchema;
	}

	/**
	 * Builds a validator from the root spawn-rule schema file. The full $ref closure
	 * reachable from it (the spawn schemas in the same version directory plus the shared
	 * client_server/common schemas they reference) is loaded and patched eagerly, so
	 * no raw (unpatched) schema file is ever fetched lazily by SchemaStorage.
	 *
	 * When $expectedSchemaVersion is given, the root schema's x-format-version is checked
	 * against it and a SchemaSetupException is thrown on mismatch.
	 *
	 * @phpstan-throws SchemaSetupException
	 */
	public static function fromSchemaTree(string $rootSchemaFile, ?string $expectedSchemaVersion = null) : self{
		$retriever = new UriRetriever();
		$retriever->setUriRetriever(new class extends FileGetContents{
			public function retrieve($uri){
				return parent::retrieve(rawurldecode($uri));
			}
		});
		$storage = new SchemaStorage($retriever);

		// Resolve the full $ref closure from the root schema and pre-register every file
		// through patchNode, instead of lazily letting SchemaStorage fetch shared refs raw.
		$rootReal = (string) realpath($rootSchemaFile);
		if($rootReal === "" || !is_file($rootReal)){
			throw new SchemaSetupException("Spawn Rules.json root schema not found: $rootSchemaFile");
		}

		$loaded = [];
		$pending = [$rootReal];
		while($pending !== []){
			$path = (string) array_pop($pending);
			$real = (string) realpath($path);
			if($real === "" || isset($loaded[$real])){
				continue;
			}
			$loaded[$real] = true;
			$schema = json_decode((string) file_get_contents($real));
			if(!$schema instanceof \stdClass){
				continue;
			}
			$patched = self::patchNode($schema, dirname($real));
			if(!$patched instanceof \stdClass){
				continue;
			}
			$storage->addSchema("file://" . $real, $patched);
			self::collectRefTargets($schema, $real, $pending);
		}

		if($expectedSchemaVersion !== null){
			$rootRaw = json_decode((string) file_get_contents($rootReal));
			$declared = $rootRaw instanceof \stdClass ? ($rootRaw->{"x-format-version"} ?? null) : null;
			if(!is_string($declared) || $declared !== $expectedSchemaVersion){
				throw new SchemaSetupException(sprintf(
					"Spawn Rules.json declares x-format-version \"%s\" but version \"%s\" was requested.",
					is_string($declared) ? $declared : "?",
					$expectedSchemaVersion
				));
			}
		}

		$rootSchema = $storage->getSchema("file://" . $rootReal);
		if(!$rootSchema instanceof \stdClass){
			throw new SchemaSetupException("The spawn-rules root schema did not resolve to an object.");
		}

		return new self(new Factory($storage), $rootSchema);
	}

	/**
	 * Enqueues the real file target of every $ref, including ones already rewritten to
	 * absolute file:// URIs, so the closure (spawn schemas plus shared refs) is all
	 * registered through patchNode before validation.
	 *
	 * @param list<string> $pending
	 */
	private static function collectRefTargets(mixed $node, string $path, array &$pending) : void{
		$base = dirname($path);
		$visit = function(mixed $n) use (&$visit, $base, &$pending) : void{
			if($n instanceof \stdClass){
				$ref = $n->{"\$ref"} ?? null;
				if(is_string($ref)){
					$target = str_starts_with($ref, "file://")
						? (string) str_replace("file://", "", rawurldecode($ref))
						: $base . "/" . rawurldecode(str_replace("\\", "/", $ref));
					if(is_file($target)){
						$pending[] = $target;
					}
				}
				foreach(get_object_vars($n) as $value){
					$visit($value);
				}
			}elseif(is_array($n)){
				foreach($n as $value){
					$visit($value);
				}
			}
		};
		$visit($node);
	}

	/**
	 * Validates one spawn-rules body (the value of "minecraft:spawn_rules").
	 *
	 * @return list<string> human-readable validation errors (empty when valid)
	 */
	public function validate(object $spawnRules) : array{
		$validator = new Validator($this->factory);
		try{
			$validator->validate($spawnRules, $this->rootSchema);
		}catch(\Throwable $e){
			return ["schema validation crashed: " . $e->getMessage()];
		}
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
	 * oneOf -> anyOf, and relative $refs -> absolute file:// URIs (recursively).
	 */
	private static function patchNode(mixed $node, string $baseDir) : mixed{
		if($node instanceof \stdClass){
			$out = new \stdClass();
			foreach(get_object_vars($node) as $key => $value){
				if($key === "oneOf"){
					$out->anyOf = self::patchNode($value, $baseDir);
				}elseif($key === "\$ref" && is_string($value)){
					$target = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $baseDir . "/" . rawurldecode($value));
					$real = realpath($target);
					if($real === false){
						throw new SchemaSetupException("Cannot resolve schema ref \"$value\" from \"$baseDir\"");
					}
					$out->{"\$ref"} = "file://" . $real;
				}else{
					$out->{$key} = self::patchNode($value, $baseDir);
				}
			}

			return $out;
		}
		if(is_array($node)){
			return array_map(static fn($value) => self::patchNode($value, $baseDir), $node);
		}

		return $node;
	}
}

/**
 * Signals an unusable or mismatched schema set (missing root, unresolved $ref, or a
 * requested schema version the root does not declare). Shared by the merge tool and the
 * test suite.
 */
final class SchemaSetupException extends \RuntimeException{

}
