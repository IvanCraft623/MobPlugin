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
 * Merges the vanilla entity spawn rules from the pinned Mojang/bedrock-samples checkout into
 * a single deterministic JSON document, keyed by every file's description.identifier.
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
 * structural key must be a known one. A failure means the vanilla data drifted beyond what the plugin was built
 * against — update the loader / component registry (and, if intended, the pinned schema
 * version and the generated artifacts) before recompiling:
 *
 *   php tools/spawn-rules/generate-schema.php
 *
 * Usage:
 *   php tools/spawn-rules/compile.php
 *
 * The samples, their commit and the schema version all come from the mojang/bedrock-samples
 * package pinned in composer.json. CI regenerates and fails on any diff.
 *
 * Exit codes: 0 = success, 1 = failure (no partial output is ever written).
 */

namespace IvanCraft623\MobPlugin\tools\spawnrules\compile;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/SpawnRuleSchemaValidator.php";
require_once __DIR__ . "/BedrockSamples.php";

use FilesystemIterator;
use IvanCraft623\MobPlugin\tools\spawnrules\BedrockSamples;
use IvanCraft623\MobPlugin\tools\spawnrules\SpawnRuleSchemaValidator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use function array_keys;
use function array_map;
use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function get_object_vars;
use function implode;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error_msg;
use function ksort;
use function printf;
use function sort;
use function sprintf;
use function strlen;
use function strtolower;
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

function main() : int{
	try{
		$schemaVersion = BedrockSamples::getSchemaVersion();
		$samplesDir = BedrockSamples::getInstallPath();
		$schemaDir = $samplesDir . "/" . SCHEMA_PATH . "/" . $schemaVersion;
		$schemaValidator = SpawnRuleSchemaValidator::fromSchemaTree($schemaDir . "/Spawn Rules.json", $schemaVersion);
		$files = listRuleFiles($samplesDir . "/" . SOURCE_PATH);
	}catch(\RuntimeException $e){
		return fail($e->getMessage());
	}
	if(count($files) === 0){
		return fail("No spawn rule JSON files found in: $samplesDir/" . SOURCE_PATH);
	}

	$errors = [];
	$merged = [];
	$sources = []; // identifier -> source file, for duplicate detection
	foreach($files as $file){
		try{
			[$identifier, $body, $spawnRules] = parseRuleFile($file);
		}catch(\RuntimeException $e){
			$errors[] = $e->getMessage();
			continue;
		}
		foreach(array_keys(get_object_vars($body)) as $rootKey){
			if($rootKey !== "format_version" && $rootKey !== "minecraft:spawn_rules"){
				$errors[] = sprintf("\"%s\" (%s): unknown structural key \"%s\" at the file root", $identifier, basename($file), $rootKey);
			}
		}
		foreach(validateSpawnRules($spawnRules, $schemaValidator) as $schemaError){
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

	$commit = BedrockSamples::getReference();
	$gameVersion = readGameVersion($samplesDir);

	$json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	if($json === false){
		return fail("Failed to encode merged spawn rules: " . json_last_error_msg());
	}
	$json .= "\n";

	$notice = buildNotice($merged, $commit, $gameVersion, $schemaVersion);

	// Both outputs are written only after every source file merged successfully.
	$outDir = dirname(__DIR__, 2) . "/resources/spawning";
	foreach([$outDir . "/spawn_rules.json" => $json, $outDir . "/NOTICE.md" => $notice] as $path => $contents){
		if(file_put_contents($path, $contents) === false){
			return fail("Failed to write: $path");
		}
	}

	printf("Merged %d spawn rule file(s) from %s\n", count($merged), $samplesDir);
	printf("Game version: %s\n", $gameVersion);
	printf("Source commit: %s\n", $commit ?? "unknown");
	printf("Schema validation: %d entr%s valid against schema version %s\n", count($merged), count($merged) === 1 ? "y" : "ies", $schemaVersion);
	printf("Wrote %s/spawn_rules.json (%d bytes) and NOTICE.md (%d bytes)\n", $outDir, strlen($json), strlen($notice));

	return 0;
}

function fail(string $message) : int{
	fwrite(STDERR, "ERROR: $message\n");

	return 1;
}

/**
 * @return array{string, stdClass, stdClass} identifier, the byte-faithful file body and its
 *                                           "minecraft:spawn_rules" object
 */
function parseRuleFile(string $file) : array{
	$raw = file_get_contents($file);
	if($raw === false){
		throw new \RuntimeException("Cannot read file: $file");
	}
	// Objects (not assoc arrays) are decoded on purpose: empty JSON objects must survive the
	// round-trip as {} instead of being mangled into [].
	try{
		$decoded = json_decode(stripJsonComments($raw), false, 512, JSON_THROW_ON_ERROR);
	}catch(JsonException $e){
		throw new \RuntimeException("Invalid JSON in \"$file\": " . $e->getMessage());
	}
	if(!$decoded instanceof stdClass){
		throw new \RuntimeException("Root of \"$file\" should be a JSON object.");
	}
	$formatVersion = $decoded->{"format_version"} ?? null;
	if(!is_string($formatVersion) || $formatVersion === ""){
		throw new \RuntimeException("Missing or invalid \"format_version\" in \"$file\".");
	}
	$spawnRules = $decoded->{"minecraft:spawn_rules"} ?? null;
	if(!$spawnRules instanceof stdClass){
		throw new \RuntimeException("Missing or invalid \"minecraft:spawn_rules\" object in \"$file\".");
	}
	$description = $spawnRules->{"description"} ?? null;
	$identifier = $description instanceof stdClass ? ($description->{"identifier"} ?? null) : null;
	if(!is_string($identifier) || $identifier === ""){
		throw new \RuntimeException("Missing or invalid \"description.identifier\" in \"$file\".");
	}

	return [$identifier, $decoded, $spawnRules];
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

/**
 * Schema-compatibility gate for one merged body. Two checks, in order of value:
 *
 *  1. Shape — the body validates against the official draft-07 schema (patched unions).
 *  2. Structure — unknown keys at the structural levels would be silently ignored by the
 *     runtime loader, so they fail here instead.
 *
 * A component the plugin doesn't know is the runtime loader's to reject: its parse test
 * runs on the merged file.
 *
 * @phpstan-return list<string>
 */
function validateSpawnRules(stdClass $spawnRules, SpawnRuleSchemaValidator $schemaValidator) : array{
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
 * @param array<string, mixed> $merged identifier -> spawn rule body
 */
function buildNotice(array $merged, ?string $commit, string $gameVersion, string $schemaVersion) : string{
	return implode("\n", [
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
		"",
		"The merger strips comments (some vanilla files are not strict JSON), keys every entry by its",
		"`description.identifier`, sorts identifiers and pretty-prints. **No other transformation is",
		"applied** — keys, values and structure are byte-faithful to the source data. Every merged",
		"entry is validated against the pinned spawn schemas; the merge fails closed on any drift.",
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
}

exit(main());
