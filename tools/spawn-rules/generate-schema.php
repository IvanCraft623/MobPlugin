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

/**
 * Spawn-schema artifact generator.
 *
 * Reads the official Mojang spawn-rule JSON schemas from the pinned Mojang/bedrock-samples
 * checkout and generates the typed PHP artifacts the plugin's drift detection consumes:
 *
 *   - src/IvanCraft623/MobPlugin/spawning/parse/schema/VanillaSpawnConditions.php
 *     One constant per "minecraft:*" spawn condition the schema declares (a pure
 *     collection of the vanilla component names). SpawnRulesParser registers a
 *     parser for every name (enforced by the PHPUnit suite), so a renamed or removed
 *     component breaks PHPStan instead of silently mis-parsing.
 *
 *   - src/IvanCraft623/MobPlugin/spawning/parse/schema/SpawnSchema.php
 *     Schema facts as typed constants: the schema version, the difficulty names
 *     accepted by difficulty_filter, and the envelope keys the loader navigates with.
 *
 *   - src/IvanCraft623/MobPlugin/spawning/parse/schema/model/*Data.php
 *     One JsonMapper payload model per payload-bearing component schema.
 *
 * Output is deterministic (sorted cases, fixed formatting), so CI regenerates and fails on
 * any diff — a stale artifact means Mojang changed something and the plugin needs a
 * conscious update.
 *
 * Usage:
 *   php tools/spawn-rules/generate-schema.php
 *
 * The samples and the schema version come from the mojang/bedrock-samples package pinned
 * in composer.json.
 *
 * Exit codes: 0 = success, 1 = failure.
 */

namespace IvanCraft623\MobPlugin\tools\spawnrules\generate;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/BedrockSamples.php";

use IvanCraft623\MobPlugin\tools\spawnrules\BedrockSamples;
use stdClass;
use function array_diff;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_values;
use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function get_object_vars;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function ksort;
use function preg_replace;
use function printf;
use function rawurldecode;
use function rtrim;
use function scandir;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;
use function unlink;
use function var_export;
use const SORT_STRING;
use const STDERR;

const SCHEMA_REPO_PATH = "metadata/json_schemas/server/spawn";
const CONDITION_PREFIX = "minecraft:";
const SCHEMA_NAMESPACE = "IvanCraft623\\MobPlugin\\spawning\\parse\\schema";

/**
 * Everything every generated file starts with, up to its namespace.
 */
const FILE_HEADER = <<<'PHP'
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

PHP;

/**
 * Schema files that are NOT per-condition payloads and so never get a XxxData model: the
 * component *envelope* (BiomeConditions lists every component), the document envelope
 * (Rules / Description), and the difficulty enum (SpawnDifficulty Legacy). Everything
 * else in the spawn schema dir that declares properties is generated a model — see
 * readConditionsModels().
 *
 * @var array<string, true>
 */
const NON_MODEL_SCHEMAS = [
	"Spawn BiomeConditions.json" => true,
	"Spawn Rules.json" => true,
	"Spawn Description.json" => true,
	"SpawnDifficulty Legacy.json" => true,
];

function main() : int{
	try{
		$schemaVersion = BedrockSamples::getSchemaVersion();
		$schemaDir = BedrockSamples::getInstallPath() . "/" . SCHEMA_REPO_PATH . "/" . $schemaVersion;
		if(!is_dir($schemaDir)){
			return fail("Spawn schema directory does not exist: $schemaDir\nSet the mojang/bedrock-samples package version in composer.json to a schema version its commit ships.");
		}
		[$declaredVersion, $conditions] = readConditionsInventory($schemaDir);
		$difficulties = readDifficultyCases($schemaDir);
		$envelopeKeys = readEnvelopeKeys($schemaDir);
		$models = readConditionsModels($schemaDir);
	}catch(\RuntimeException $e){
		return fail($e->getMessage());
	}
	if($declaredVersion !== $schemaVersion){
		return fail(sprintf(
			"Schemas declare x-format-version \"%s\" but version \"%s\" was requested; set the mojang/bedrock-samples package version in composer.json to %s.",
			$declaredVersion,
			$schemaVersion,
			$declaredVersion
		));
	}

	$outDir = dirname(__DIR__, 2) . "/src/IvanCraft623/MobPlugin/spawning/parse/schema";
	$modelDir = $outDir . "/model";
	$artifacts = [
		$outDir . "/VanillaSpawnConditions.php" => buildConditionsArtifact($schemaVersion, $conditions),
		$outDir . "/SpawnSchema.php" => buildSchemaArtifact($schemaVersion, $difficulties, $envelopeKeys),
	];
	foreach(buildConditionsModelsArtifact($models) as $name => $contents){
		$artifacts[$modelDir . "/" . $name] = $contents;
	}

	// model/ holds generated classes only: a model regeneration no longer produces (a
	// component Mojang removed) is stale.
	foreach(array_values(array_diff(glob($modelDir . "/*.php") ?: [], array_keys($artifacts))) as $path){
		if(!unlink($path)){
			return fail("Failed to delete: $path");
		}
		printf("Deleted %s\n", $path);
	}
	foreach($artifacts as $path => $contents){
		if(file_put_contents($path, $contents) === false){
			return fail("Failed to write: $path");
		}
		printf("Wrote %s (%d bytes)\n", $path, strlen($contents));
	}
	printf("Generated %d component case(s), %d difficulty case(s), %d model class(es) from schema version %s\n", count($conditions), count($difficulties), count($models), $schemaVersion);

	return 0;
}

function fail(string $message) : int{
	fwrite(STDERR, "ERROR: $message\n");

	return 1;
}

function loadSchemaFile(string $path) : stdClass{
	$raw = file_get_contents($path);
	if($raw === false){
		throw new \RuntimeException("Cannot read schema file: $path");
	}
	$decoded = json_decode($raw);
	if(!$decoded instanceof stdClass){
		throw new \RuntimeException("Schema file is not a JSON object: $path");
	}

	return $decoded;
}

/**
 * Component inventory of the pinned schema: raw "minecraft:*" property name -> the schema
 * file that defines the component's shape ($ref), one entry per declared component.
 *
 * @return array{0: string, 1: array<string, string>} schema version and inventory
 */
function readConditionsInventory(string $schemaDir) : array{
	$doc = loadSchemaFile($schemaDir . "/Spawn BiomeConditions.json");
	$version = $doc->{"x-format-version"} ?? null;
	if(!is_string($version) || $version === ""){
		throw new \RuntimeException("Spawn BiomeConditions.json is missing its \"x-format-version\".");
	}
	$properties = $doc->properties ?? null;
	if(!$properties instanceof stdClass){
		throw new \RuntimeException("Spawn BiomeConditions.json has no \"properties\" object.");
	}

	$inventory = [];
	foreach(get_object_vars($properties) as $rawName => $property){
		if(!$property instanceof stdClass){
			throw new \RuntimeException("Spawn BiomeConditions.json property \"$rawName\" is not an object.");
		}
		$inventory[$rawName] = resolveShapeRef($property);
	}
	if(count($inventory) === 0){
		throw new \RuntimeException("Spawn BiomeConditions.json declares no components.");
	}
	ksort($inventory, SORT_STRING);

	return [$version, $inventory];
}

/**
 * The $ref naming a component's shape file. Direct refs are used as-is; for oneOf-shaped
 * unions (object | list of objects) every branch points at the same shape file, so that
 * ref is used. Anything else is an inline shape.
 */
function resolveShapeRef(stdClass $property) : string{
	$ref = $property->{"\$ref"} ?? null;
	if(is_string($ref) && $ref !== ""){
		return rawurldecode($ref);
	}
	$refs = [];
	$oneOf = $property->oneOf ?? null;
	if(is_array($oneOf)){
		foreach($oneOf as $branch){
			if(!$branch instanceof stdClass){
				continue;
			}
			$branchRef = $branch->{"\$ref"} ?? null;
			if(!is_string($branchRef) && isset($branch->items) && $branch->items instanceof stdClass){
				$branchRef = $branch->items->{"\$ref"} ?? null;
			}
			if(is_string($branchRef) && $branchRef !== ""){
				$refs[rawurldecode($branchRef)] = true;
			}
		}
	}

	return count($refs) === 1 ? (string) array_key_first($refs) : "inline shape";
}

/**
 * @return list<string>
 */
function readDifficultyCases(string $schemaDir) : array{
	$doc = loadSchemaFile($schemaDir . "/SpawnDifficulty Legacy.json");
	$cases = $doc->enum ?? null;
	if(!is_array($cases) || count($cases) === 0){
		throw new \RuntimeException("SpawnDifficulty Legacy.json declares no enum values.");
	}
	/** @var list<string> $result */
	$result = [];
	foreach($cases as $case){
		if(!is_string($case)){
			throw new \RuntimeException("SpawnDifficulty Legacy.json enum contains a non-string value.");
		}
		$result[] = $case;
	}

	return $result;
}

/**
 * Derives the XxxData models for every payload-bearing component schema in the spawn
 * schema dir. A schema becomes a model iff its filename is not a NON_MODEL_SCHEMAS
 * envelope/enum file AND it declares at least one property (empty marker schemas carry no
 * payload). Each model's class name is the schema filename (without "Spawn " / ".json")
 * plus "Data" — e.g. "Spawn DelayFilter.json" → DelayFilterData.
 *
 * The set is derived from the directory (not a hand-maintained list), so a component
 * added by Mojang automatically gets a model.
 *
 * @return array<string, list<array{string, string, string}>> model class -> field
 *     descriptors in schema order, each [fieldName, phpType, "required"/"nullable"]
 */
function readConditionsModels(string $schemaDir) : array{
	$entries = scandir($schemaDir);
	if($entries === false){
		throw new \RuntimeException("Cannot list schema directory: $schemaDir");
	}
	$models = [];
	foreach($entries as $schemaFile){
		if(!str_ends_with($schemaFile, ".json") || isset(NON_MODEL_SCHEMAS[$schemaFile])){
			continue;
		}

		$doc = loadSchemaFile($schemaDir . "/" . $schemaFile);
		$properties = $doc->properties ?? null;
		if(!$properties instanceof stdClass || count(get_object_vars($properties)) === 0){
			continue; // empty marker / structural schema: no payload to model
		}
		$required = [];
		$req = $doc->required ?? null;
		if(is_array($req)){
			foreach($req as $name){
				if(is_string($name)){
					$required[$name] = true;
				}
			}
		}

		$fields = [];
		foreach(get_object_vars($properties) as $rawName => $prop){
			if(!$prop instanceof stdClass){
				throw new \RuntimeException("$schemaFile property \"$rawName\" is not an object.");
			}
			$fields[] = describeField($rawName, $prop, isset($required[$rawName]));
		}
		$models[modelClassName($schemaFile)] = $fields;
	}
	ksort($models, SORT_STRING);

	return $models;
}

/**
 * "Spawn X.json" -> "X", "SpawnAboveBlockFilter" etc. (the marker-free basename).
 */
function modelClassName(string $schemaFile) : string{
	$base = basename($schemaFile, ".json");
	$base = str_starts_with($base, "Spawn ") ? substr($base, strlen("Spawn ")) : $base;

	return $base . "Data";
}

/**
 * Maps one schema property to a POJO field descriptor: the PHP type and (for optional
 * fields) whether it is null-defaulting. Required fields are non-null; the schema's
 * declared `default` is NOT turned into an active field initializer — MobPlugin treats an
 * absent optional field as null (no restriction), so generated fields are nullable and
 * the condition mapping applies the real default exactly as the hand-written parser did.
 *
 * @return array{string, string, string} [fieldName, phpType, "required"/"nullable"]
 */
function describeField(string $rawName, stdClass $prop, bool $isRequired) : array{
	// A $ref (e.g. to the difficulty enum, or a legacy Reference string id) names a
	// string-valued payload; the schema may still expose an ordinal x-underlying-type,
	// so a present $ref wins over the numeric hint. Otherwise map concrete types. An
	// array-typed property (default [] — e.g. spawns_above_block_filter.blocks) is a
	// nullable list of strings.
	$propType = $prop->{"x-underlying-type"} ?? $prop->type ?? null;
	$phpType = match(true){
		isset($prop->{"\$ref"}) => "string",
		$propType === "int32", $propType === "uint32", $propType === "uint64", $propType === "integer" => "int",
		$propType === "boolean" => "bool",
		$propType === "string" => "string",
		$propType === "array", is_array($prop->default ?? null) => "array",
		default => throw new \RuntimeException("Unsupported schema property type " . var_export($propType, true) . " for \"$rawName\""),
	};

	return [$rawName, $phpType, $isRequired ? "required" : "nullable"];
}

/**
 * Envelope keys the schema declares on each document level, as property names. These are
 * the structural JSON keys the loader navigates with (as opposed to the per-condition
 * components of Spawn BiomeConditions.json).
 *
 * @return array<string, list<string>> schema file basename -> its top-level property names
 */
function readEnvelopeKeys(string $schemaDir) : array{
	$keys = [];
	foreach(["Spawn Rules.json", "Spawn Description.json"] as $fileName){
		$doc = loadSchemaFile($schemaDir . "/" . $fileName);
		$properties = $doc->properties ?? null;
		if(!$properties instanceof stdClass){
			throw new \RuntimeException("$fileName has no \"properties\" object.");
		}
		$names = array_keys(get_object_vars($properties));
		if(count($names) === 0){
			throw new \RuntimeException("$fileName declares no properties.");
		}
		sort($names, SORT_STRING);
		$keys[$fileName] = $names;
	}

	return $keys;
}

/**
 * A complete generated file: the shared header, the namespace and $body.
 */
function phpFile(string $namespace, string $body) : string{
	return FILE_HEADER . "\nnamespace $namespace;\n\n" . $body . "\n";
}

/**
 * @param array<string, string> $conditions raw condition name -> shape $ref, sorted
 */
function buildConditionsArtifact(string $schemaVersion, array $conditions) : string{
	$members = [];
	foreach($conditions as $rawName => $ref){
		$members[] = sprintf(
			"\tpublic const %s = \"%s\"; // \$ref: %s",
			toConstant($rawName),
			normalizeConditionName($rawName),
			$ref
		);
	}

	return phpFile(SCHEMA_NAMESPACE, sprintf(<<<'PHP'
/**
 * Auto-generated from the Mojang spawn-rule schemas (%s) — do not edit by hand.
 *
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class VanillaSpawnConditions{

%s

	private function __construct(){}

	/**
	 * Every spawn condition name this schema declares, reflected from the constants.
	 *
	 * @phpstan-return list<string>
	 */
	public static function getAll() : array{
		/** @var list<string> $names */
		$names = (new \ReflectionClass(self::class))->getConstants();

		return $names;
	}
}
PHP, $schemaVersion, implode("\n", $members)));
}

/**
 * @param list<string>                $difficulties
 * @param array<string, list<string>> $envelopeKeys schema file basename -> property names
 */
function buildSchemaArtifact(string $schemaVersion, array $difficulties, array $envelopeKeys) : string{
	$difficultyCases = "[" . implode(", ", array_map(static fn(string $case) : string => "\"$case\"", $difficulties)) . "]";

	$envelopeLines = [];
	foreach($envelopeKeys as $fileName => $names){
		foreach($names as $name){
			$envelopeLines[] = "\t/** Envelope key \"$name\" the loader uses to navigate spawn-rule documents (declared by $fileName). */";
			$envelopeLines[] = sprintf("\tpublic const KEY_%s = \"%s\";", toConstant($name), $name);
			$envelopeLines[] = "";
		}
	}

	return phpFile(SCHEMA_NAMESPACE, sprintf(<<<'PHP'
/**
 * Auto-generated from the Mojang spawn-rule JSON schemas — do not edit by hand.
 *
 * Schema facts the runtime consumes as defaults and the PHPUnit suite uses to pin the
 * artifact version. Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class SpawnSchema{
	/** Official spawn-schema version this artifact was generated from. */
	public const SCHEMA_VERSION = "%s";

	/**
	 * Difficulty names the schema declares for difficulty_filter, in ascending order.
	 *
	 * @var list<string>
	 */
	public const DIFFICULTY_CASES = %s;

	/** Lowest difficulty name declared by the schema (difficulty_filter min default). */
	public const DIFFICULTY_MIN = self::DIFFICULTY_CASES[0];

	/** Highest difficulty name declared by the schema (difficulty_filter max default). */
	public const DIFFICULTY_MAX = self::DIFFICULTY_CASES[%d];

%s
}
PHP, $schemaVersion, $difficultyCases, count($difficulties) - 1, rtrim(implode("\n", $envelopeLines))));
}

function toConstant(string $rawName) : string{
	return strtoupper((string) preg_replace("/(?<!^)[A-Z]/", "_\$0", normalizeConditionName($rawName)));
}

/**
 * Builds the generated XxxData POJO classes (one per model-backed component) as a map of
 * filename -> file content. Each class is plain public data (nullable optional fields,
 * @required non-null required fields) that JsonMapper populates at runtime.
 *
 * @param array<string, list<array{string, string, string}>> $models
 *                                                                   model class -> field descriptors [name, phpType, "required"/"nullable"]
 *
 * @phpstan-return array<string, string>
 */
function buildConditionsModelsArtifact(array $models) : array{
	$files = [];
	foreach($models as $className => $fields){
		$fieldLines = [];
		foreach($fields as [$name, $phpType, $mode]){
			$declType = $mode === "required" ? $phpType : "?" . $phpType;
			$default = $mode === "required" ? "" : " = null";
			// Array-valued fields need a @var element hint for JsonMapper; scalar/required
			// fields use their native type (with @required when mandatory).
			if($phpType === "array"){
				$fieldLines[] = "\t/** @var string[] */";
			}elseif($mode === "required"){
				$fieldLines[] = "\t/** @required */";
			}
			$fieldLines[] = "\tpublic $declType \$$name$default;";
		}
		$files[$className . ".php"] = phpFile(SCHEMA_NAMESPACE . "\\model", sprintf(<<<'PHP'
/**
 * Auto-generated from the Mojang spawn-rule JSON schemas — do not edit by hand.
 *
 * Payload data model for a spawn-rule condition (JsonMapper / ComponentParseContext).
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class %s{
%s
}
PHP, $className, implode("\n", $fieldLines)));
	}

	return $files;
}

function normalizeConditionName(string $rawName) : string{
	return str_starts_with($rawName, CONDITION_PREFIX) ? substr($rawName, strlen(CONDITION_PREFIX)) : $rawName;
}

exit(main());
