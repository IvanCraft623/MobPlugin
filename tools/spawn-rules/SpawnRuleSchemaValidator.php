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

use FilesystemIterator;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Uri\Retrievers\FileGetContents;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use function array_map;
use function array_slice;
use function basename;
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
use function strtolower;
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
 * Both the merge tool (tools/spawn-rules/compile.php) and the PHPUnit suite use this
 * class, so offline validation of the committed resource runs against the same schemas.
 */
final class SpawnRuleSchemaValidator{
	private readonly Factory $factory;
	private readonly \stdClass $rootSchema;

	private function __construct(Factory $factory, \stdClass $rootSchema){
		$this->factory = $factory;
		$this->rootSchema = $rootSchema;
	}

	/**
	 * Builds a validator from a schema directory tree. The directory must keep the
	 * relative layout the schema files reference (e.g. resources/spawning/schemas or a
	 * bedrock-samples checkout's metadata/json_schemas).
	 *
	 * When $expectedSchemaVersion is given, the root schema's x-format-version is checked
	 * and a SchemaSetupException is thrown on mismatch.
	 *
	 * @phpstan-throws SchemaSetupException
	 */
	public static function fromSchemaTree(string $schemaTree, ?string $expectedSchemaVersion = null) : self{
		$retriever = new UriRetriever();
		$retriever->setUriRetriever(new class extends FileGetContents{
			public function retrieve($uri){
				return parent::retrieve(rawurldecode($uri));
			}
		});
		$storage = new SchemaStorage($retriever);

		$rootReal = null;
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($schemaTree, FilesystemIterator::SKIP_DOTS));
		foreach($iterator as $fileInfo){
			/** @var SplFileInfo $fileInfo */
			if(!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== "json"){
				continue;
			}
			$schema = json_decode((string) file_get_contents($fileInfo->getPathname()));
			if(!$schema instanceof \stdClass){
				continue;
			}
			$real = (string) $fileInfo->getRealPath();
			$storage->addSchema("file://" . $real, self::patchNode($schema, dirname($real)));
			if(basename($real) === "Spawn Rules.json"){
				$rootReal = $real;
			}
		}
		if($rootReal === null){
			throw new SchemaSetupException("Spawn Rules.json root schema not found under: $schemaTree");
		}

		if($expectedSchemaVersion !== null){
			$rootRaw = json_decode((string) file_get_contents($rootReal));
			$declared = $rootRaw->{"x-format-version"} ?? null;
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
