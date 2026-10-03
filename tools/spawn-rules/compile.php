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
 * Merges the vanilla spawn rules of the pinned mojang/bedrock-samples package into
 * resources/spawning/spawn_rules.json (keyed by identifier) and writes its NOTICE.md.
 *
 * Values are copied as they are; only comments are stripped. Every file must validate
 * against the pinned schemas, or nothing is written. CI regenerates and fails on any
 * diff. Usage: php tools/spawn-rules/compile.php
 */

namespace IvanCraft623\MobPlugin\tools\spawnrules\compile;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/SpawnRuleSchemaValidator.php";
require_once __DIR__ . "/BedrockSamples.php";

use IvanCraft623\MobPlugin\tools\spawnrules\BedrockSamples;
use IvanCraft623\MobPlugin\tools\spawnrules\SpawnRuleSchemaValidator;
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
use function sprintf;
use function strlen;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;
use const STDERR;

const SOURCE_REPO = "https://github.com/Mojang/bedrock-samples";
const SCHEMA_PATH = "metadata/json_schemas/server/spawn";

function main() : int{
	try{
		$schemaVersion = BedrockSamples::getSchemaVersion();
		$samplesDir = BedrockSamples::getInstallPath();
		$schemaDir = $samplesDir . "/" . SCHEMA_PATH . "/" . $schemaVersion;
		$schemaValidator = SpawnRuleSchemaValidator::fromSchemaTree($schemaDir . "/Spawn Rules.json", $schemaVersion);
		$files = BedrockSamples::listSpawnRuleFiles();
	}catch(\RuntimeException $e){
		return fail($e->getMessage());
	}
	if(count($files) === 0){
		return fail("No spawn rule JSON files found in: $samplesDir/" . BedrockSamples::SPAWN_RULES_PATH);
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
 * @return array{string, stdClass, stdClass} identifier, file body, its "minecraft:spawn_rules"
 */
function parseRuleFile(string $file) : array{
	[$spawnRules, $decoded] = BedrockSamples::readSpawnRuleFile($file);
	$formatVersion = $decoded->{"format_version"} ?? null;
	if(!is_string($formatVersion) || $formatVersion === ""){
		throw new \RuntimeException("Missing or invalid \"format_version\" in \"$file\".");
	}
	$description = $spawnRules->{"description"} ?? null;
	$identifier = $description instanceof stdClass ? ($description->{"identifier"} ?? null) : null;
	if(!is_string($identifier) || $identifier === ""){
		throw new \RuntimeException("Missing or invalid \"description.identifier\" in \"$file\".");
	}

	return [$identifier, $decoded, $spawnRules];
}

/**
 * Validates against the schema, then rejects structural keys the loader would ignore.
 *
 * @phpstan-return list<string> errors
 */
function validateSpawnRules(stdClass $spawnRules, SpawnRuleSchemaValidator $schemaValidator) : array{
	$errors = $schemaValidator->validate($spawnRules);
	if($errors !== []){
		return array_map(static fn(string $error) => "schema: $error", $errors);
	}

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
		"| Source path | `" . BedrockSamples::SPAWN_RULES_PATH . "` |",
		"| Source commit | `" . ($commit ?? "unknown") . "` |",
		"| Game version | " . $gameVersion . " |",
		"| Schema validation | `" . SCHEMA_PATH . "/" . $schemaVersion . "` |",
		"| Merged entities | " . count($merged) . " |",
		"| Merged by | `tools/spawn-rules/compile.php` |",
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
