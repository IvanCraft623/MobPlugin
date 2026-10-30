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
 * resources/bedrock/spawning/spawn_rules.json, and names the spawn events they send in
 * VanillaSpawnEvents. Every file must validate against the pinned schemas, or nothing is
 * written.
 */

namespace IvanCraft623\MobPlugin\tools\spawnrules\compile;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/SpawnRuleSchemaValidator.php";
require_once __DIR__ . "/../bedrock/BedrockSamples.php";

use IvanCraft623\MobPlugin\tools\bedrock\BedrockSamples;
use IvanCraft623\MobPlugin\tools\spawnrules\SpawnRuleSchemaValidator;
use stdClass;
use function array_keys;
use function array_map;
use function basename;
use function count;
use function dirname;
use function file_put_contents;
use function fwrite;
use function get_object_vars;
use function implode;
use function is_string;
use function json_encode;
use function json_last_error_msg;
use function ksort;
use function preg_match;
use function printf;
use function sort;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;
use const STDERR;

const EVENT_PREFIX = "minecraft:";
const EVENTS_NAMESPACE = "IvanCraft623\\MobPlugin\\spawning\\event";

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

	$json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
	if($json === false){
		return fail("Failed to encode merged spawn rules: " . json_last_error_msg());
	}
	$json .= "\n";

	try{
		$eventsClass = buildEventsArtifact(collectEvents($merged));
	}catch(\RuntimeException $e){
		return fail($e->getMessage());
	}

	// The outputs are written only after every source file merged successfully.
	$outDir = dirname(__DIR__, 2) . "/resources/bedrock";
	$eventsPath = dirname(__DIR__, 2) . "/src/IvanCraft623/MobPlugin/spawning/event/VanillaSpawnEvents.php";
	foreach([$outDir . "/spawning/spawn_rules.json" => $json, $eventsPath => $eventsClass] as $path => $contents){
		if(file_put_contents($path, $contents) === false){
			return fail("Failed to write: $path");
		}
	}

	printf("Merged %d spawn rule file(s) from %s\n", count($merged), $samplesDir);
	printf("Schema validation: %d entr%s valid against schema version %s\n", count($merged), count($merged) === 1 ? "y" : "ies", $schemaVersion);
	printf("Wrote %s/spawning/spawn_rules.json (%d bytes)\n", $outDir, strlen($json));

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
 * The events the rules send: of herds, of their first members, and the ones a permuted mob
 * is created with ("minecraft:pillager<minecraft:spawn_as_patrol_follower>").
 *
 * @phpstan-param array<string, stdClass> $rules identifier => file body
 * @phpstan-return list<string> sorted
 */
function collectEvents(array $rules) : array{
	$events = [];
	foreach($rules as $body){
		foreach($body->{"minecraft:spawn_rules"}->{"conditions"} ?? [] as $condition){
			foreach(asList($condition->{"minecraft:herd"} ?? []) as $herd){
				foreach([$herd->{"event"} ?? null, $herd->{"initial_event"} ?? null] as $event){
					if(is_string($event)){
						$events[$event] = true;
					}
				}
			}
			foreach(asList($condition->{"minecraft:permute_type"} ?? []) as $permutation){
				$type = $permutation->{"entity_type"} ?? "";
				$start = strpos($type, "<");
				if($start !== false){
					$events[substr($type, $start + 1, -1)] = true;
				}
			}
		}
	}
	$events = array_keys($events);
	sort($events, SORT_STRING);

	return $events;
}

/**
 * A component holds one entry or a list of them.
 *
 * @phpstan-param stdClass|list<stdClass> $value
 * @phpstan-return list<stdClass>
 */
function asList(stdClass|array $value) : array{
	return $value instanceof stdClass ? [$value] : $value;
}

/**
 * @phpstan-param list<string> $events
 */
function buildEventsArtifact(array $events) : string{
	$lines = [];
	foreach($events as $event){
		$constant = strtoupper(substr($event, strlen(EVENT_PREFIX)));
		if(!str_starts_with($event, EVENT_PREFIX) || preg_match('/^[A-Z][A-Z0-9_]*$/', $constant) !== 1){
			throw new \RuntimeException("The spawn event \"$event\" doesn't make a constant name");
		}
		$lines[] = sprintf("\tpublic const %s = \"%s\";", $constant, $event);
	}

	return FILE_HEADER . sprintf(<<<'PHP'

namespace %s;

/**
 * Auto-generated from the spawn rules of the pinned Mojang bedrock-samples — do not edit by
 * hand. The vanilla spawn events, to register handlers with.
 *
 * Regenerate with: php tools/spawn-rules/compile.php
 */
final class VanillaSpawnEvents{

%s

	private function __construct(){}
}

PHP, EVENTS_NAMESPACE, implode("\n", $lines));
}

exit(main());
