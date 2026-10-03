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
 * Generates src/IvanCraft623/MobPlugin/spawning/parse/schema/ from the pinned
 * mojang/bedrock-samples package: constants for what the spawn schemas and rule files
 * declare, and one JsonMapper model per component payload. CI regenerates and fails on
 * any diff. Usage: php tools/spawn-rules/generate-schema.php
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
use function is_file;
use function is_string;
use function json_decode;
use function ksort;
use function preg_match;
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
const BIOME_FILTER_COMPONENT = "minecraft:biome_filter";
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
 * Spawn schemas that describe envelopes or enums, not a component payload.
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
		[$filterTestKeys, $filterGroupKeys] = readFilterKeys($schemaDir, $conditions);
		[$populationControls, $filterTests, $filterOperators] = readRuleValues();
		$dataArtifacts = [
			"VanillaMobCategories" => ["Every mob category they use, as written in a rule's \"population_control\".", namedConstants($populationControls)],
			"VanillaBiomeFilterTestNames" => ["The name of every test their biome filters use, as written in a filter's \"test\" key.", namedConstants($filterTests)],
			"VanillaBiomeFilterOperators" => ["Every comparison their biome filters use, as written in a filter's \"operator\" key.", namedConstants($filterOperators)],
		];
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
		$outDir . "/VanillaBiomeFilterKeys.php" => buildFilterKeysArtifact($filterTestKeys, $filterGroupKeys),
	];
	foreach($dataArtifacts as $className => [$summary, $constants]){
		$artifacts[$outDir . "/" . $className . ".php"] = buildRuleValuesArtifact($className, $summary, $constants);
	}
	foreach(buildConditionsModelsArtifact($models) as $name => $contents){
		$artifacts[$modelDir . "/" . $name] = $contents;
	}

	// A model no longer generated belongs to a component Mojang removed.
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
 * @return array{0: string, 1: array<string, string>} schema version, and raw component name -> shape $ref
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
 * The shape file a component refers to, directly or through every branch of a oneOf.
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
 * A model for every schema with properties, except NON_MODEL_SCHEMAS.
 *
 * @return array<string, list<array{string, string, string}>> model class -> [field, PHP type, "required"/"nullable"]
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
 * "Spawn DelayFilter.json" -> "DelayFilterData".
 */
function modelClassName(string $schemaFile) : string{
	$base = basename($schemaFile, ".json");
	$base = str_starts_with($base, "Spawn ") ? substr($base, strlen("Spawn ")) : $base;

	return $base . "Data";
}

/**
 * Optional fields are nullable rather than defaulted: the parser applies the defaults.
 *
 * @return array{string, string, string} [field, PHP type, "required"/"nullable"]
 */
function describeField(string $rawName, stdClass $prop, bool $isRequired) : array{
	// A $ref (the difficulty enum) is a string even when the schema hints an ordinal type.
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
 * @return array<string, list<string>> schema file -> its property names
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
 * @param array<string, string> $conditions raw component name -> shape $ref
 *
 * @return array{list<string>, list<string>} the keys of a filter test and of a filter group
 */
function readFilterKeys(string $schemaDir, array $conditions) : array{
	$ref = $conditions[BIOME_FILTER_COMPONENT] ?? throw new \RuntimeException("The schema declares no " . BIOME_FILTER_COMPONENT . " component.");
	$filterDir = dirname($schemaDir . "/" . $ref);
	$keys = [];
	foreach(["Filter Test.json", "Filter Group Map.json"] as $fileName){
		$path = $filterDir . "/" . $fileName;
		if(!is_file($path)){
			throw new \RuntimeException("$fileName is not next to the schema " . BIOME_FILTER_COMPONENT . " refers to ($ref).");
		}
		$properties = loadSchemaFile($path)->properties ?? null;
		if(!$properties instanceof stdClass || count(get_object_vars($properties)) === 0){
			throw new \RuntimeException("$fileName declares no properties.");
		}
		$names = array_keys(get_object_vars($properties));
		sort($names, SORT_STRING);
		$keys[] = $names;
	}

	return [$keys[0], $keys[1]];
}

/**
 * Values the schemas leave free strings, as the rule files use them.
 *
 * @return array{list<string>, list<string>, list<string>} population controls, filter tests, filter operators
 */
function readRuleValues() : array{
	$files = BedrockSamples::listSpawnRuleFiles();
	if(count($files) === 0){
		throw new \RuntimeException("No spawn rule files found in " . BedrockSamples::SPAWN_RULES_PATH . ".");
	}
	$found = ["population_control" => [], "filter test" => [], "filter operator" => []];
	foreach($files as $file){
		[$spawnRules] = BedrockSamples::readSpawnRuleFile($file);
		$populationControl = ($spawnRules->description ?? null) instanceof stdClass ? ($spawnRules->description->population_control ?? null) : null;
		if(!is_string($populationControl) || $populationControl === ""){
			throw new \RuntimeException("No population_control in: $file");
		}
		$found["population_control"][$populationControl] = true;

		$conditions = $spawnRules->conditions ?? [];
		foreach(is_array($conditions) ? $conditions : [$conditions] as $condition){
			if($condition instanceof stdClass && isset($condition->{BIOME_FILTER_COMPONENT})){
				$found["filter test"] += collectFilterValues($condition->{BIOME_FILTER_COMPONENT}, "test");
				$found["filter operator"] += collectFilterValues($condition->{BIOME_FILTER_COMPONENT}, "operator");
			}
		}
	}

	$sorted = [];
	foreach($found as $what => $values){
		if(count($values) === 0){
			throw new \RuntimeException("The pinned spawn rules use no $what at all.");
		}
		$names = array_keys($values);
		sort($names, SORT_STRING);
		$sorted[] = $names;
	}

	return [$sorted[0], $sorted[1], $sorted[2]];
}

/**
 * @return array<string, true> every string the filter gives the key, at any depth
 */
function collectFilterValues(mixed $node, string $key) : array{
	$found = [];
	if($node instanceof stdClass){
		$value = $node->{$key} ?? null;
		if(is_string($value)){
			$found[$value] = true;
		}
		$node = get_object_vars($node);
	}
	if(is_array($node)){
		foreach($node as $child){
			$found += collectFilterValues($child, $key);
		}
	}

	return $found;
}

/**
 * Constant names for values that aren't identifiers.
 */
const SYMBOL_NAMES = [
	"==" => "EQUALS",
	"!=" => "NOT_EQUALS",
];

/**
 * @param list<string> $values
 *
 * @return array<string, string> constant name -> value, sorted by name
 */
function namedConstants(array $values) : array{
	$constants = [];
	foreach($values as $value){
		$name = SYMBOL_NAMES[$value] ?? (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1 ? toConstant($value) : null);
		if($name === null){
			throw new \RuntimeException("The spawn rules use \"$value\", which can't name a constant; add it to SYMBOL_NAMES.");
		}
		if(isset($constants[$name])){
			throw new \RuntimeException("\"$value\" and \"{$constants[$name]}\" would both be the constant $name; rename one in SYMBOL_NAMES.");
		}
		$constants[$name] = $value;
	}
	ksort($constants, SORT_STRING);

	return $constants;
}

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
 * Schema facts the loader consumes: envelope keys, defaults and the difficulty names.
 * Regenerate with: php tools/spawn-rules/generate-schema.php
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

/**
 * @param list<string> $testKeys
 * @param list<string> $groupKeys
 */
function buildFilterKeysArtifact(array $testKeys, array $groupKeys) : string{
	$lines = [];
	foreach(["FIELD" => $testKeys, "GROUP" => $groupKeys] as $prefix => $keys){
		$constants = [];
		foreach($keys as $key){
			// Group aliases come in both cases ("AND", "all"): the name is kept as declared.
			$constants[$prefix . "_" . (strtoupper($key) === $key ? $key : toConstant($key))] = $key;
		}
		ksort($constants, SORT_STRING);
		foreach($constants as $name => $key){
			$lines[] = sprintf("\tpublic const %s = \"%s\";", $name, $key);
		}
		$lines[] = "";
	}

	return phpFile(SCHEMA_NAMESPACE, sprintf(<<<'PHP'
/**
 * Auto-generated from the Mojang filter schemas biome_filter refers to — do not edit by
 * hand.
 *
 * The keys of a filter node: FIELD_* are the fields of a single test, GROUP_* the keys
 * that hold a group of nodes.
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class VanillaBiomeFilterKeys{

%s

	private function __construct(){}
}
PHP, rtrim(implode("\n", $lines))));
}

/**
 * @param array<string, string> $constants constant name -> value
 */
function buildRuleValuesArtifact(string $className, string $summary, array $constants) : string{
	$lines = [];
	foreach($constants as $name => $value){
		$lines[] = sprintf("\tpublic const %s = \"%s\";", $name, $value);
	}

	return phpFile(SCHEMA_NAMESPACE, sprintf(<<<'PHP'
/**
 * Auto-generated from the pinned Mojang spawn rules — do not edit by hand.
 *
 * %s
 * The schema leaves it a free string, so this is the data's own inventory.
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class %s{

%s

	private function __construct(){}
}
PHP, $summary, $className, implode("\n", $lines)));
}

function toConstant(string $rawName) : string{
	return strtoupper((string) preg_replace("/(?<!^)[A-Z]/", "_\$0", normalizeConditionName($rawName)));
}

/**
 * @param array<string, list<array{string, string, string}>> $models
 *
 * @phpstan-return array<string, string> file name -> contents
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
