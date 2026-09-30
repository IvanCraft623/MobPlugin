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
 * Reads the official Mojang spawn-rule JSON schemas from a pinned checkout of
 * Mojang/bedrock-samples and generates the typed PHP artifacts the plugin's drift
 * detection consumes:
 *
 *   - src/IvanCraft623/MobPlugin/spawning/parse/schema/VanillaSpawnConditions.php
 *     One constant per "minecraft:*" spawn condition the schema declares (a pure
 *     collection of the vanilla component names). SpawnRulesParser registers a
 *     parser for every name (enforced by the PHPUnit suite), so a renamed or removed
 *     component breaks PHPStan instead of silently mis-parsing.
 *
 *   - src/IvanCraft623/MobPlugin/spawning/parse/schema/SpawnSchema.php
 *     Schema facts as typed constants: the schema version, the difficulty names
 *     accepted by difficulty_filter, and the light-level bounds of brightness_filter.
 *
 * Output is deterministic (sorted cases, fixed formatting), so `--check` can verify in
 * CI that the committed artifacts still match the official schemas — a stale artifact
 * means Mojang changed something and the plugin needs a conscious update.
 *
 * Usage:
 *   php tools/spawn-rules/generate-schema.php --schema-version=<version> [--samples-dir=<path>] [--out=<dir>] [--check]
 *
 * --schema-version is required (single-sourced from tools/spawn-rules/SCHEMA_VERSION).
 *
 * Exit codes: 0 = success / in sync, 1 = failure or drift detected.
 */

const TOOL_VERSION = "1.0.0";
const SOURCE_REPO = "https://github.com/Mojang/bedrock-samples";
const SCHEMA_REPO_PATH = "metadata/json_schemas/server/spawn";
const CONDITION_PREFIX = "minecraft:";

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

/**
 * @param list<string> $argv
 */
function main(array $argv) : int{
	$opts = getopt("", ["samples-dir::", "schema-version::", "out::", "check"]);
	if(!is_array($opts)){
		return fail("Unable to parse command line options.");
	}
	$schemaVersion = readStringOption($opts, "schema-version");
	if($schemaVersion === null){
		return fail("Missing required --schema-version=<version> (see tools/spawn-rules/SCHEMA_VERSION).");
	}
	$samplesDir = readStringOption($opts, "samples-dir") ?? dirname(__DIR__, 2) . "/vendor/mojang/bedrock-samples";
	$outDir = readStringOption($opts, "out") ?? dirname(__DIR__, 2) . "/src/IvanCraft623/MobPlugin/spawning/parse/schema";
	$check = isset($opts["check"]);

	$schemaDir = $samplesDir . "/" . SCHEMA_REPO_PATH . "/" . $schemaVersion;
	if(!is_dir($schemaDir)){
		return fail(sprintf(
			"Spawn schema directory does not exist: %s\nClone %s (the same commit the spawn-rules data was merged from) or pass --samples-dir.",
			$schemaDir,
			SOURCE_REPO
		));
	}

	try{
		[$declaredVersion, $conditions] = readConditionsInventory($schemaDir);
		$difficulties = readDifficultyCases($schemaDir);
		[$brightnessMin, $brightnessMax] = readBrightnessBounds($schemaDir);
		$envelopeKeys = readEnvelopeKeys($schemaDir);
		$models = readConditionsModels($schemaDir);
	}catch(SchemaParseException $e){
		return fail($e->getMessage());
	}
	if($declaredVersion !== $schemaVersion){
		return fail(sprintf(
			"Schemas declare x-format-version \"%s\" but version \"%s\" was requested; pass --schema-version=%s (or pin the requested one).",
			$declaredVersion,
			$schemaVersion,
			$declaredVersion
		));
	}

	$conditionsPhp = buildConditionsArtifact($schemaVersion, $conditions);
	$schemaPhp = buildSchemaArtifact($schemaVersion, $difficulties, $brightnessMin, $brightnessMax, $envelopeKeys);
	$modelPhp = buildConditionsModelsArtifact($schemaVersion, $models);

	if($check){
		return checkArtifacts($outDir, array_merge([
			"VanillaSpawnConditions.php" => $conditionsPhp,
			"SpawnSchema.php" => $schemaPhp,
		], $modelPhp));
	}

	if(!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)){
		return fail("Unable to create output directory: $outDir");
	}
	foreach(["VanillaSpawnConditions.php" => $conditionsPhp, "SpawnSchema.php" => $schemaPhp] as $name => $contents){
		$path = $outDir . "/" . $name;
		if(file_put_contents($path, $contents) === false){
			return fail("Failed to write: $path");
		}
		printf("Wrote %s (%d bytes)\n", $path, strlen($contents));
	}
	$modelDir = $outDir . "/model";
	if(!is_dir($modelDir) && !mkdir($modelDir, 0777, true) && !is_dir($modelDir)){
		return fail("Unable to create model output directory: $modelDir");
	}
	foreach($modelPhp as $name => $contents){
		$path = $modelDir . "/" . $name;
		if(file_put_contents($path, $contents) === false){
			return fail("Failed to write: $path");
		}
		printf("Wrote %s (%d bytes)\n", $path, strlen($contents));
	}
	printf("Generated %d component case(s), %d difficulty case(s), %d model class(es) from schema version %s\n", count($conditions), count($difficulties), count($modelPhp), $schemaVersion);

	return 0;
}

/**
 * @param array<string, mixed> $opts
 */
function readStringOption(array $opts, string $name) : ?string{
	$value = $opts[$name] ?? null;

	return is_string($value) && $value !== "" ? $value : null;
}

function fail(string $message) : int{
	fwrite(STDERR, "ERROR: $message\n");

	return 1;
}

/**
 * json_encode returns `string|false`; here the input is always a string so it can never
 * fail. Coerce to the string form for PHPStan (clamped to a literal "null" on the
 * impossible failure branch).
 */
function jsonEncodeString(string $value) : string{
	$encoded = json_encode($value);

	return is_string($encoded) ? $encoded : "null";
}

/**
 * @phpstan-throws SchemaParseException
 */
function loadSchemaFile(string $path) : stdClass{
	$raw = file_get_contents($path);
	if($raw === false){
		throw new SchemaParseException("Cannot read schema file: $path");
	}
	$decoded = json_decode($raw);
	if(!$decoded instanceof stdClass){
		throw new SchemaParseException("Schema file is not a JSON object: $path");
	}

	return $decoded;
}

/**
 * Component inventory of the pinned schema: raw "minecraft:*" property name -> the schema
 * file that defines the component's shape ($ref), one entry per declared component.
 *
 * @return array{0: string, 1: array<string, string>} schema version and inventory
 * @phpstan-throws SchemaParseException
 */
function readConditionsInventory(string $schemaDir) : array{
	$doc = loadSchemaFile($schemaDir . "/Spawn BiomeConditions.json");
	$version = $doc->{"x-format-version"} ?? null;
	if(!is_string($version) || $version === ""){
		throw new SchemaParseException("Spawn BiomeConditions.json is missing its \"x-format-version\".");
	}
	$properties = $doc->properties ?? null;
	if(!$properties instanceof stdClass){
		throw new SchemaParseException("Spawn BiomeConditions.json has no \"properties\" object.");
	}

	$inventory = [];
	foreach(get_object_vars($properties) as $rawName => $property){
		if(!$property instanceof stdClass){
			throw new SchemaParseException("Spawn BiomeConditions.json property \"$rawName\" is not an object.");
		}
		$inventory[$rawName] = resolveShapeRef($property);
	}
	if(count($inventory) === 0){
		throw new SchemaParseException("Spawn BiomeConditions.json declares no components.");
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
 * @phpstan-throws SchemaParseException
 */
function readDifficultyCases(string $schemaDir) : array{
	$doc = loadSchemaFile($schemaDir . "/SpawnDifficulty Legacy.json");
	$cases = $doc->enum ?? null;
	if(!is_array($cases) || count($cases) === 0){
		throw new SchemaParseException("SpawnDifficulty Legacy.json declares no enum values.");
	}
	/** @var list<string> $result */
	$result = [];
	foreach($cases as $case){
		if(!is_string($case)){
			throw new SchemaParseException("SpawnDifficulty Legacy.json enum contains a non-string value.");
		}
		$result[] = $case;
	}

	return $result;
}

/**
 * @return array{0: int, 1: int}
 * @phpstan-throws SchemaParseException
 */
function readBrightnessBounds(string $schemaDir) : array{
	$doc = loadSchemaFile($schemaDir . "/Spawn BrightnessFilter.json");
	$properties = $doc->properties ?? null;
	if(!$properties instanceof stdClass || !isset($properties->min, $properties->max)){
		throw new SchemaParseException("Spawn BrightnessFilter.json has no min/max properties.");
	}
	$min = $properties->min->minimum ?? null;
	$max = $properties->max->maximum ?? null;
	if(!is_int($min) || !is_int($max)){
		throw new SchemaParseException("Spawn BrightnessFilter.json does not declare integer minimum/maximum bounds.");
	}

	return [$min, $max];
}

/**
 * Derives the XxxData models for every payload-bearing component schema in the spawn
 * schema dir. A schema becomes a model iff its filename is not a NON_MODEL_SCHEMAS
 * envelope/enum file AND it declares at least one property (empty marker schemas carry no
 * payload). Each model's class name is the schema filename (without "Spawn " / ".json")
 * plus "Data" — e.g. "Spawn DelayFilter.json" → DelayFilterData.
 *
 * The set is derived from the directory (not a hand-maintained list), so a component
 * added by Mojang automatically gets a model; `--check` then enforces the committed
 * artifact matches.
 *
 * @return array<string, list<array{string, string, string}>> model class -> field
 *     descriptors, each [fieldName, phpType, "required"/"nullable"]
 * @phpstan-throws SchemaParseException
 */
function readConditionsModels(string $schemaDir) : array{
	$models = [];
	foreach(scandir($schemaDir) as $entry){
		if(!is_string($entry) || !str_ends_with($entry, ".json")){
			continue;
		}
		$schemaFile = $entry;
		if(isset(NON_MODEL_SCHEMAS[$schemaFile])){
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
				throw new SchemaParseException("$schemaFile property \"$rawName\" is not an object.");
			}
			$fields[] = describeField($rawName, $prop, isset($required[$rawName]));
		}
		ksort($fields, SORT_STRING);
		$className = modelClassName($schemaFile);
		$models[$className] = $fields;
	}
	ksort($models, SORT_STRING);

	return $models;
}

/**
 * "Spawn X.json" -> "X", "SpawnAboveBlockFilter" etc. (the marker-free basename).
 */
function modelClassName(string $schemaFile) : string{
	$base = substr($schemaFile, 0, strpos($schemaFile, ".json"));
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
 * @return array{0: string, 1: string, 2: string, 3: bool}
 * @phpstan-throws SchemaParseException
 */
function describeField(string $rawName, stdClass $prop, bool $isRequired) : array{
	// A $ref (e.g. to the difficulty enum, or a legacy Reference string id) names a
	// string-valued payload; the schema may still expose an ordinal x-underlying-type,
	// so a present $ref wins over the numeric hint. Otherwise map concrete types. An
	// array-typed property (default [] — e.g. spawns_above_block_filter.blocks) is a
	// nullable list of strings.
	$ref = stdClassPropOrNull($prop, "\$ref");
	$default = stdClassPropOrNull($prop, "default");
	$propType = stdClassPropOrNull($prop, "x-underlying-type") ?? stdClassPropOrNull($prop, "type") ?? null;
	$phpType = null;
	if($ref !== null){
		$phpType = "string";
	}elseif($propType === "int32" || $propType === "uint32" || $propType === "uint64" || $propType === "integer"){
		$phpType = "int";
	}elseif($propType === "boolean"){
		$phpType = "bool";
	}elseif($propType === "string"){
		$phpType = "string";
	}elseif($propType === "array" || is_array($default)){
		$phpType = "array";
	}
	if($phpType === null){
		throw new SchemaParseException("Unsupported schema property type \"{$propType}\" for \"$rawName\"");
	}

	return [$rawName, $phpType, $isRequired ? "required" : "nullable"];
}

/**
 * Reads an optional stdClass property without tripping the SPL "Undefined property"
 * warning when it is absent. Returns null when the property is not set.
 */
function stdClassPropOrNull(stdClass $o, string $key) : mixed{
	return isset($o->{$key}) ? $o->{$key} : null;
}

/**
 * Envelope keys the schema declares on each document level, as property names. These are
 * the structural JSON keys the loader navigates with (as opposed to the per-condition
 * components of Spawn BiomeConditions.json).
 *
 * @return array<string, list<string>> schema file basename -> its top-level property names
 * @phpstan-throws SchemaParseException
 */
function readEnvelopeKeys(string $schemaDir) : array{
	$keys = [];
	foreach(["Spawn Rules.json", "Spawn Description.json"] as $fileName){
		$doc = loadSchemaFile($schemaDir . "/" . $fileName);
		$properties = $doc->properties ?? null;
		if(!$properties instanceof stdClass){
			throw new SchemaParseException("$fileName has no \"properties\" object.");
		}
		$names = array_keys(get_object_vars($properties));
		if(count($names) === 0){
			throw new SchemaParseException("$fileName declares no properties.");
		}
		sort($names, SORT_STRING);
		$keys[$fileName] = $names;
	}

	return $keys;
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

	return sprintf(<<<'PHP'
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

namespace IvanCraft623\MobPlugin\spawning\parse\schema;

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
PHP, $schemaVersion, implode("\n", $members)) . "\n";
}

/**
 * @param list<string>                $difficulties
 * @param array<string, list<string>> $envelopeKeys schema file basename -> property names
 */
function buildSchemaArtifact(string $schemaVersion, array $difficulties, int $brightnessMin, int $brightnessMax, array $envelopeKeys) : string{
	$difficultyCases = sprintf(
		"[%s]",
		implode(", ", array_map(static fn(string $case) : string => jsonEncodeString($case), $difficulties))
	);

	$envelopeLines = [];
	foreach($envelopeKeys as $fileName => $names){
		foreach($names as $name){
			$envelopeLines[] = sprintf(
				"\t/** Envelope key \"%s\" the loader uses to navigate spawn-rule documents (declared by %s). */",
				$name,
				$fileName
			);
			$envelopeLines[] = sprintf("\tpublic const KEY_%s = %s;", toConstant($name), json_encode($name));
			$envelopeLines[] = "";
		}
	}
	$envelopeBlock = implode("\n", $envelopeLines);

	return sprintf(<<<'PHP'
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

namespace IvanCraft623\MobPlugin\spawning\parse\schema;

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

	/**
	 * Light-level bounds vanilla brightness_filter accepts, as declared by the schema.
	 */
	public const BRIGHTNESS_MIN = %s;
	public const BRIGHTNESS_MAX = %s;

%s
}
PHP, $schemaVersion, $difficultyCases, count($difficulties) - 1, var_export($brightnessMin, true), var_export($brightnessMax, true), rtrim($envelopeBlock)) . "\n";
}

function toConstant(string $rawName) : string{
	return strtoupper((string) preg_replace("/(?<!^)[A-Z]/", "_\$0", normalizeConditionName($rawName)));
}

/**
 * Builds the generated XxxData POJO classes (one per model-backed component) as a map of
 * filename -> file content. Each class is plain public data (nullable optional fields,
 * @required non-null required fields) that JsonMapper populates at runtime.
 *
 * @param array<string, list<array{string, string, string, bool}>> $models
 *     model class -> field descriptors [name, phpType, "required"/"nullable", isRequired]
 *
 * @phpstan-return array<string, string>
 */
function buildConditionsModelsArtifact(string $schemaVersion, array $models) : array{
	$files = [];
	foreach($models as $className => $fields){
		$fieldLines = [];
		foreach($fields as $field){
			$name = $field[0];
			$phpType = $field[1];
			$mode = $field[2];
			$declType = $mode === "required" ? $phpType : "?" . $phpType;
			$default = $mode === "required" ? "" : " = null";
			// Array-valued fields need a @var element hint for JsonMapper; scalar/required
			// fields use their native type (with @required when mandatory).
			$doc = null;
			if($phpType === "array"){
				$doc = "\t/** @var string[] */";
			}elseif($mode === "required"){
				$doc = "\t/** @required */";
			}
			$fieldLines[] = $doc === null
				? sprintf("\tpublic %s \$%s%s;", $declType, $name, $default)
				: sprintf("%s\n\tpublic %s \$%s%s;", $doc, $declType, $name, $default);
		}
		$files[$className . ".php"] = sprintf(<<<'PHP'
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

namespace IvanCraft623\MobPlugin\spawning\parse\schema\model;

/**
 * Auto-generated from the Mojang spawn-rule JSON schemas — do not edit by hand.
 *
 * Payload data model for a spawn-rule condition (JsonMapper / ComponentParseContext).
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class %s{
%s}
PHP, $className, rtrim(implode("\n", $fieldLines)) . "\n") . "\n";
	}

	return $files;
}

function normalizeConditionName(string $rawName) : string{
	return str_starts_with($rawName, CONDITION_PREFIX) ? substr($rawName, strlen(CONDITION_PREFIX)) : $rawName;
}

/**
 * @param array<string, string> $artifacts filename -> expected content
 */
function checkArtifacts(string $outDir, array $artifacts) : int{
	$drifted = false;
	foreach($artifacts as $name => $expected){
		// Model classes live in a "model" subdirectory; the two root artifacts do not.
		$path = ($name === "VanillaSpawnConditions.php" || $name === "SpawnSchema.php")
			? $outDir . "/" . $name
			: $outDir . "/model/" . $name;
		$actual = is_file($path) ? (string) file_get_contents($path) : null;
		if($actual === $expected){
			printf("In sync: %s\n", $path);
			continue;
		}
		$drifted = true;
		fwrite(STDERR, "DRIFT: $path does not match regeneration from the pinned schemas.\n");
		if($actual === null){
			fwrite(STDERR, "  (file is missing)\n");
		}else{
			fwrite(STDERR, renderDiff($actual, $expected));
		}
	}
	if($drifted){
		fwrite(STDERR, "\nThe official spawn schemas changed; regenerate the artifacts and review the diff:\n  php tools/spawn-rules/generate-schema.php\n");

		return 1;
	}

	return 0;
}

/**
 * Unified-style diff of two line sequences: "-" lines are the committed artifact,
 * "+" lines are what regeneration from the pinned schemas produces.
 */
function renderDiff(string $actual, string $expected) : string{
	$a = explode("\n", $actual);
	$b = explode("\n", $expected);
	$n = count($a);
	$m = count($b);
	$lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
	for($i = $n - 1; $i >= 0; $i--){
		for($j = $m - 1; $j >= 0; $j--){
			$lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
		}
	}

	$out = "";
	$i = 0;
	$j = 0;
	while($i < $n && $j < $m){
		if($a[$i] === $b[$j]){
			$i++;
			$j++;
		}elseif($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]){
			$out .= "- " . $a[$i++] . "\n";
		}else{
			$out .= "+ " . $b[$j++] . "\n";
		}
	}
	while($i < $n){
		$out .= "- " . $a[$i++] . "\n";
	}
	while($j < $m){
		$out .= "+ " . $b[$j++] . "\n";
	}

	return $out;
}

final class SchemaParseException extends Exception{

}

exit(main($argv));
