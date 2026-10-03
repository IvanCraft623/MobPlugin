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
 * resources/spawning/spawn_rules.json and writes its NOTICE.md. Every file must validate
 * against the pinned schemas, or nothing is written.
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
	if($gameVersion === null){
		return fail("Failed to read the game version from $samplesDir/version.json");
	}

	$json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	if($json === false){
		return fail("Failed to encode merged spawn rules: " . json_last_error_msg());
	}
	$json .= "\n";

	$notice = buildNotice($commit, $gameVersion, $schemaVersion);

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
	if(count($errors) !== 0){
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
 * @return string|null game version from the samples' version.json, or null if it can't be read
 */
function readGameVersion(string $samplesDir) : ?string{
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

	return null;
}

function buildNotice(?string $commit, string $gameVersion, string $schemaVersion) : string{
	return implode("\n", [
		"# Vanilla Bedrock data",
		"",
		"`spawn_rules.json` is the vanilla spawn rules merged into one file: comments stripped, entries",
		"keyed and sorted by identifier, values unchanged.",
		"",
		"| | |",
		"|---|---|",
		"| Game version | " . $gameVersion . " |",
		"| Spawn schema version | " . $schemaVersion . " |",
		"| Source | [Mojang/bedrock-samples](" . SOURCE_REPO . ") |",
		"| Commit | `" . ($commit ?? "unknown") . "` |",
		"| Path | `" . BedrockSamples::SPAWN_RULES_PATH . "` |",
		"",
		"The spawn schema classes (`spawning/parse/schema/`) and the entity data (`EntityIds`,",
		"`VanillaEntitySizes`) are generated from the same commit. To regenerate, see",
		"`docs/spawning.md`.",
		"",
	]);
}

exit(main());
