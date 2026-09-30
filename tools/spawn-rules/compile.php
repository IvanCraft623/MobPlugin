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
 * MobPlugin spawn-rules merger tool.
 *
 * Merges the vanilla entity spawn rules from a checkout of Mojang/bedrock-samples into a
 * single deterministic JSON document, keyed by every file's description.identifier.
 *
 * This tool is a PURE MERGER, by design it does NOT:
 *  - rename "minecraft:<name>" keys,
 *  - normalize union shapes (string|list|object),
 *  - alias legacy block ids,
 *  - whitelist / drop components, or
 *  - resolve biome tags.
 *
 * Contents stay byte-faithful to the source data; the only transformations are stripping
 * comments outside strings (some vanilla files are not strict JSON) and reformatting. All
 * parsing semantics belong to the plugin's runtime loader
 * (src/IvanCraft623/MobPlugin/spawning/parse/SpawnRulesParser.php).
 *
 * The merge doubles as the schema-compatibility gate: every merged body is validated
 * against the official Mojang spawn schemas of the pinned version, and every
 * condition component and structural key must be declared by the pinned schema
 * inventory. A failure means the vanilla data drifted beyond what the plugin was built
 * against — update the loader / component registry (and, if intended, the pinned schema
 * version and the generated artifacts) before recompiling:
 *
 *   php tools/spawn-rules/generate-schema.php
 *
 * Usage:
 *   php tools/spawn-rules/compile.php [--check] [--samples-dir=<path>] [--out=<path>] [--source-commit=<sha>] [--schema-version=<version>]
 *
 * The samples directory, commit and schema version default to the mojang/bedrock-samples
 * package pinned in composer.json. --check compares instead of writing, for CI.
 *
 * Exit codes: 0 = success / in sync, 1 = failure or drift (no partial output is ever written).
 */

namespace IvanCraft623\MobPlugin\tools\spawnrules\compile;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/SpawnRuleSchemaValidator.php";
require_once __DIR__ . "/BedrockSamples.php";

use Exception;
use FilesystemIterator;
use IvanCraft623\MobPlugin\tools\spawnrules\BedrockSamples;
use IvanCraft623\MobPlugin\tools\spawnrules\SchemaSetupException;
use IvanCraft623\MobPlugin\tools\spawnrules\SpawnRuleSchemaValidator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use function array_keys;
use function array_map;
use function array_merge;
use function basename;
use function count;
use function dirname;
use function escapeshellarg;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function get_object_vars;
use function getopt;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error_msg;
use function ksort;
use function mkdir;
use function printf;
use function shell_exec;
use function sort;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;
use const STDERR;

const TOOL_VERSION = "1.3.0";
const SOURCE_REPO = "https://github.com/Mojang/bedrock-samples";
const SOURCE_PATH = "behavior_pack/spawn_rules";
const SCHEMA_PATH = "metadata/json_schemas/server/spawn";
const CONDITION_PREFIX = "minecraft:";

/**
 * @param list<string> $argv
 */
function main(array $argv) : int{
	$opts = getopt("", ["samples-dir::", "out::", "source-commit::", "schema-version::", "check"]);
	if(!is_array($opts)){
		return fail("Unable to parse command line options.");
	}
	$customSamplesDir = readStringOption($opts, "samples-dir");
	try{
		$schemaVersion = readStringOption($opts, "schema-version") ?? BedrockSamples::getSchemaVersion();
		$samplesDir = $customSamplesDir ?? BedrockSamples::getInstallPath();
	}catch(\RuntimeException $e){
		return fail($e->getMessage());
	}
	$outDir = readStringOption($opts, "out") ?? dirname(__DIR__, 2) . "/resources/spawning";
	$commitOverride = readStringOption($opts, "source-commit");
	$check = isset($opts["check"]);

	if(!is_dir($samplesDir)){
		return fail("Samples directory does not exist: $samplesDir");
	}
	$rulesDir = $samplesDir . "/" . SOURCE_PATH;
	if(!is_dir($rulesDir)){
		return fail("Spawn rules directory does not exist inside the samples checkout: $rulesDir");
	}
	$schemaDir = $samplesDir . "/" . SCHEMA_PATH . "/" . $schemaVersion;
	if(!is_dir($schemaDir)){
		return fail("Spawn schema directory does not exist inside the samples checkout: $schemaDir");
	}

	try{
		$schemaValidator = SpawnRuleSchemaValidator::fromSchemaTree($schemaDir . "/Spawn Rules.json", $schemaVersion);
		$inventory = readSchemaConditionsInventory($schemaDir);
	}catch(SchemaSetupException $e){
		return fail($e->getMessage());
	}

	$files = listRuleFiles($rulesDir);
	if(count($files) === 0){
		return fail("No spawn rule JSON files found in: $rulesDir");
	}

	$errors = [];
	$merged = [];
	$sources = []; // identifier -> source file, for duplicate detection
	foreach($files as $file){
		try{
			[$identifier, $body] = parseRuleFile($file);
		}catch(JsonParseException $e){
			$errors[] = $e->getMessage();
			continue;
		}
		$spawnRules = $body->{"minecraft:spawn_rules"} ?? null;
		if(!$spawnRules instanceof stdClass){
			$errors[] = "Missing \"minecraft:spawn_rules\" object in \"$file\"."; // parseRuleFile guarantees it; unreachable
			continue;
		}
		foreach(array_keys(get_object_vars($body)) as $rootKey){
			if($rootKey !== "format_version" && $rootKey !== "minecraft:spawn_rules"){
				$errors[] = sprintf("\"%s\" (%s): unknown structural key \"%s\" at the file root", $identifier, basename($file), $rootKey);
			}
		}
		foreach(validateSpawnRules($spawnRules, $schemaValidator, $inventory, $schemaVersion) as $schemaError){
			$errors[] = sprintf("\"%s\" (%s): %s", $identifier, basename($file), $schemaError);
		}
		if(isset($sources[$identifier])){
			$errors[] = "Duplicate identifier \"$identifier\" in \"$file\" (already provided by \"{$sources[$identifier]}\").";
			continue;
		}
		$sources[$identifier] = $file;
		$merged[$identifier] = $body;
	}
	if(count($errors) !== 0){
		foreach($errors as $error){
			fwrite(STDERR, "ERROR: $error\n");
		}
		return fail(sprintf("%d source file(s) could not be merged; no output was written.", count($errors)));
	}

	ksort($merged, SORT_STRING);

	// Only ask git about a checkout that is itself a repository: git would otherwise walk
	// up and report the enclosing repository's commit.
	$commit = $commitOverride ?? ($customSamplesDir === null
		? BedrockSamples::getReference()
		: (file_exists($samplesDir . "/.git") ? resolveGitValue($samplesDir, "rev-parse HEAD") : null));
	$gameVersion = readGameVersion($samplesDir);

	$json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	if($json === false){
		return fail("Failed to encode merged spawn rules: " . json_last_error_msg());
	}
	$json .= "\n";

	$notice = buildNotice($merged, $commit, $gameVersion, $schemaVersion);

	$rulesPath = $outDir . "/spawn_rules.json";
	$noticePath = $outDir . "/NOTICE.md";
	if($check){
		return checkOutputs([$rulesPath => $json, $noticePath => $notice]);
	}
	if(!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)){
		return fail("Unable to create output directory: $outDir");
	}

	// Both outputs are written only after every source file merged successfully.
	if(file_put_contents($rulesPath, $json) === false){
		return fail("Failed to write: $rulesPath");
	}
	if(file_put_contents($noticePath, $notice) === false){
		return fail("Failed to write: $noticePath");
	}

	printf("Merged %d spawn rule file(s) from %s\n", count($merged), $samplesDir);
	printf("Game version: %s\n", $gameVersion);
	printf("Source commit: %s\n", $commit ?? "unknown");
	printf("Schema validation: %d entr%s valid against schema version %s\n", count($merged), count($merged) === 1 ? "y" : "ies", $schemaVersion);
	printf("Wrote %s (%d bytes)\n", $rulesPath, strlen($json));
	printf("Wrote %s (%d bytes)\n", $noticePath, strlen($notice));

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
 * @return array{0: string, 1: stdClass} identifier and the file body, byte-faithful
 */
function parseRuleFile(string $file) : array{
	$raw = file_get_contents($file);
	if($raw === false){
		throw new JsonParseException("Cannot read file: $file");
	}
	// Objects (not assoc arrays) are decoded on purpose: empty JSON objects must survive the
	// round-trip as {} instead of being mangled into [].
	try{
		$decoded = json_decode(stripJsonComments($raw), false, 512, JSON_THROW_ON_ERROR);
	}catch(JsonException $e){
		throw new JsonParseException("Invalid JSON in \"$file\": " . $e->getMessage());
	}
	if(!$decoded instanceof stdClass){
		throw new JsonParseException("Root of \"$file\" should be a JSON object.");
	}
	$formatVersion = $decoded->{"format_version"} ?? null;
	if(!is_string($formatVersion) || $formatVersion === ""){
		throw new JsonParseException("Missing or invalid \"format_version\" in \"$file\".");
	}
	$spawnRules = $decoded->{"minecraft:spawn_rules"} ?? null;
	if(!$spawnRules instanceof stdClass){
		throw new JsonParseException("Missing or invalid \"minecraft:spawn_rules\" object in \"$file\".");
	}
	$description = $spawnRules->{"description"} ?? null;
	$identifier = $description instanceof stdClass ? ($description->{"identifier"} ?? null) : null;
	if(!is_string($identifier) || $identifier === ""){
		throw new JsonParseException("Missing or invalid \"description.identifier\" in \"$file\".");
	}

	return [$identifier, $decoded];
}

/**
 * Strips // and slash-star comments from a JSON document, ignoring comment markers inside
 * strings. Vanilla spawn rule files contain comments and are therefore not strict JSON.
 */
function stripJsonComments(string $json) : string{
	$out = "";
	$length = strlen($json);
	$i = 0;
	$inString = false;
	$escaped = false;
	while($i < $length){
		$char = $json[$i];
		if($inString){
			$out .= $char;
			if($escaped){
				$escaped = false;
			}elseif($char === "\\"){
				$escaped = true;
			}elseif($char === "\""){
				$inString = false;
			}
			$i++;
		}else{
			if($char === "\""){
				$inString = true;
				$out .= $char;
				$i++;
			}elseif($char === "/" && $i + 1 < $length && $json[$i + 1] === "/"){
				while($i < $length && $json[$i] !== "\n"){
					$i++;
				}
			}elseif($char === "/" && $i + 1 < $length && $json[$i + 1] === "*"){
				$i += 2;
				while($i + 1 < $length && !($json[$i] === "*" && $json[$i + 1] === "/")){
					$i++;
				}
				$i += 2;
			}else{
				$out .= $char;
				$i++;
			}
		}
	}

	return $out;
}

/**
 * @return list<string>
 */
function listRuleFiles(string $rulesDir) : array{
	$files = [];
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rulesDir, FilesystemIterator::SKIP_DOTS));
	/** @var \SplFileInfo $fileInfo */
	foreach($iterator as $fileInfo){
		if($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === "json"){
			$files[] = $fileInfo->getPathname();
		}
	}
	sort($files, SORT_STRING);

	return $files;
}

function resolveGitValue(string $repoDir, string $args) : ?string{
	$command = "git -C " . escapeshellarg($repoDir) . " $args 2>/dev/null";
	$output = shell_exec($command);

	return is_string($output) && trim($output) !== "" ? trim($output) : null;
}

/**
 * Component inventory of the pinned schema (the properties of Spawn BiomeConditions.json),
 * normalized to unprefixed names.
 *
 * @phpstan-return array<string, true>
 * @phpstan-throws SchemaSetupException
 */
function readSchemaConditionsInventory(string $schemaDir) : array{
	$file = $schemaDir . "/Spawn BiomeConditions.json";
	$decoded = json_decode((string) file_get_contents($file));
	$properties = $decoded instanceof stdClass ? ($decoded->properties ?? null) : null;
	if(!$properties instanceof stdClass){
		throw new SchemaSetupException("Spawn BiomeConditions.json has no \"properties\" object: $file");
	}
	$inventory = [];
	foreach(array_keys(get_object_vars($properties)) as $rawName){
		$inventory[str_starts_with($rawName, CONDITION_PREFIX) ? substr($rawName, strlen(CONDITION_PREFIX)) : $rawName] = true;
	}
	if(count($inventory) === 0){
		throw new SchemaSetupException("Spawn BiomeConditions.json declares no components: $file");
	}

	return $inventory;
}

/**
 * Schema-compatibility gate for one merged body. Three checks, in order of value:
 *
 *  1. Shape — the body validates against the official draft-07 schema (patched unions).
 *  2. Structure — unknown keys at the structural levels would be silently ignored by the
 *     runtime loader, so they fail here instead.
 *  3. Inventory — every condition component must be declared by the pinned schema; an
 *     unknown component means vanilla drifted beyond this schema version.
 *
 * @param array<string, true> $inventory pinned component inventory (unprefixed names)
 *
 * @phpstan-return list<string>
 */
function validateSpawnRules(stdClass $spawnRules, SpawnRuleSchemaValidator $schemaValidator, array $inventory, string $schemaVersion) : array{
	$errors = $schemaValidator->validate($spawnRules);
	if($errors !== []){
		return array_map(static fn(string $error) => "schema: $error", $errors);
	}

	// 2. Structure.
	foreach(array_keys(get_object_vars($spawnRules)) as $key){
		if($key !== "description" && $key !== "conditions"){
			$errors[] = "unknown structural key \"$key\" in \"minecraft:spawn_rules\"";
		}
	}

	// 3. Component inventory.
	$conditions = $spawnRules->conditions ?? null;
	$conditions = is_array($conditions) ? $conditions : [$conditions];
	$seen = [];
	foreach($conditions as $index => $condition){
		if(!$condition instanceof stdClass){
			continue; // already reported by the shape validation
		}
		foreach(array_keys(get_object_vars($condition)) as $rawKey){
			$component = str_starts_with($rawKey, CONDITION_PREFIX) ? substr($rawKey, strlen(CONDITION_PREFIX)) : $rawKey;
			if(isset($seen[$component])){
				continue;
			}
			$seen[$component] = true;
			if(!isset($inventory[$component])){
				$errors[] = sprintf("conditions[%d]: component \"%s\" is not declared by schema version %s", $index, $rawKey, $schemaVersion);
			}
		}
	}

	return $errors;
}

/**
 * @return string game version from the samples' version.json, or "unknown"
 */
function readGameVersion(string $samplesDir) : string{
	$file = $samplesDir . "/version.json";
	if(is_file($file)){
		$decoded = json_decode((string) file_get_contents($file), true);
		if(is_array($decoded)){
			$latest = $decoded["latest"] ?? null;
			if(is_array($latest)){
				$version = $latest["version"] ?? null;
				if(is_string($version)){
					return $version;
				}
			}
		}
	}

	return "unknown";
}

/**
 * @param array<string, string> $outputs path -> expected content
 */
function checkOutputs(array $outputs) : int{
	$drifted = false;
	foreach($outputs as $path => $expected){
		$actual = is_file($path) ? file_get_contents($path) : false;
		if($actual === $expected){
			printf("In sync: %s\n", $path);
			continue;
		}
		$drifted = true;
		fwrite(STDERR, "DRIFT: $path does not match a merge of the pinned samples.\n");
	}
	if($drifted){
		fwrite(STDERR, "\nRegenerate with: php tools/spawn-rules/compile.php\n");

		return 1;
	}

	return 0;
}

/**
 * @param array<string, mixed> $merged identifier -> spawn rule body
 */
function buildNotice(array $merged, ?string $commit, string $gameVersion, string $schemaVersion) : string{
	$lines = [
		"# NOTICE — vanilla spawn rules data",
		"",
		"`spawn_rules.json` in this directory is a machine-generated, deterministic merge of the",
		"vanilla **Minecraft: Bedrock Edition** entity spawn rules published by Mojang in the",
		"[bedrock-samples](https://github.com/Mojang/bedrock-samples) repository, under",
		"`behavior_pack/spawn_rules`.",
		"",
		"| Field | Value |",
		"|---|---|",
		"| Source repository | " . SOURCE_REPO . " |",
		"| Source path | `" . SOURCE_PATH . "` |",
		"| Source commit | `" . ($commit ?? "unknown") . "` |",
		"| Game version | " . $gameVersion . " |",
		"| Schema validation | `" . SCHEMA_PATH . "/" . $schemaVersion . "` |",
		"| Merged entities | " . count($merged) . " |",
		"| Merged by | `tools/spawn-rules/compile.php` v" . TOOL_VERSION . " |",
	];
	$lines = array_merge($lines, [
		"",
		"The merger strips comments (some vanilla files are not strict JSON), keys every entry by its",
		"`description.identifier`, sorts identifiers and pretty-prints. **No other transformation is",
		"applied** — keys, values and structure are byte-faithful to the source data. Every merged",
		"entry is validated against the pinned spawn schemas and its condition components are checked",
		"against the pinned schema inventory; the merge fails closed on any drift.",
		"",
		"The source material is © Mojang AB and subject to the [Minecraft End User License",
		"Agreement](https://www.minecraft.net/en-us/eula). This merged file is redistributed solely",
		"for interoperability with MobPlugin; MobPlugin is not affiliated with, endorsed by, or",
		"sponsored by Mojang AB or Microsoft. The generated schema artifacts derived from the same",
		"checkout (src/IvanCraft623/MobPlugin/spawning/parse/schema/) carry the same rationale.",
		"",
		"The source commit is pinned by the `" . BedrockSamples::PACKAGE . "` dev dependency in",
		"`composer.json`. To regenerate these files after `composer install`:",
		"",
		"```",
		"php tools/spawn-rules/compile.php",
		"php tools/spawn-rules/generate-schema.php",
		"```",
		"",
	]);

	return implode("\n", $lines);
}

final class JsonParseException extends Exception{

}

exit(main($argv));
