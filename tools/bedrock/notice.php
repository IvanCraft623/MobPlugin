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
 * Writes resources/bedrock/NOTICE.md: where the vanilla data under resources/bedrock comes
 * from. Usage: php tools/bedrock/notice.php
 */

namespace IvanCraft623\MobPlugin\tools\bedrock\notice;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/BedrockSamples.php";

use IvanCraft623\MobPlugin\tools\bedrock\BedrockSamples;
use function basename;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function implode;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function printf;
use function strlen;
use const STDERR;

const SOURCE_REPO = "https://github.com/Mojang/bedrock-samples";

function main() : int{
	try{
		$schemaVersion = BedrockSamples::getSchemaVersion();
		$samplesDir = BedrockSamples::getInstallPath();
	}catch(\RuntimeException $e){
		return fail($e->getMessage());
	}
	$gameVersion = readGameVersion($samplesDir);
	if($gameVersion === null){
		return fail("Failed to read the game version from $samplesDir/version.json");
	}

	$notice = buildNotice(BedrockSamples::getReference(), $gameVersion, $schemaVersion);
	$path = dirname(__DIR__, 2) . "/resources/bedrock/NOTICE.md";
	if(file_put_contents($path, $notice) === false){
		return fail("Failed to write: $path");
	}

	printf("Game version: %s\n", $gameVersion);
	printf("Wrote %s (%d bytes)\n", $path, strlen($notice));

	return 0;
}

function fail(string $message) : int{
	fwrite(STDERR, basename(__FILE__) . ": $message\n");

	return 1;
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
		"| | |",
		"|---|---|",
		"| Game version | " . $gameVersion . " |",
		"| Spawn schema version | " . $schemaVersion . " |",
		"| Source | [Mojang/bedrock-samples](" . SOURCE_REPO . ") |",
		"| Commit | `" . ($commit ?? "unknown") . "` |",
		"",
		"| Here | From | |",
		"|---|---|---|",
		"| `spawning/spawn_rules.json` | `" . BedrockSamples::SPAWN_RULES_PATH . "` | Merged into one file: comments stripped, entries keyed and sorted by identifier, values unchanged. |",
		"| `loot_tables.json` | `" . BedrockSamples::LOOT_TABLES_PATH . "` | The tables the entities name, and the tables those name, merged into one minified file, keyed by path without `loot_tables/` and `.json`, as are the tables `loot_table` entries name; values otherwise unchanged. |",
		"",
		"The spawn schema classes (`spawning/parse/schema/`) and the entity data (`EntityIds`,",
		"`VanillaEntitySizes`) are generated from the same commit. To regenerate, see",
		"`docs/spawning.md`.",
		"",
	]);
}

exit(main());
