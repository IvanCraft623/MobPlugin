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
 * Merges the vanilla loot tables the plugin rolls from the pinned mojang/bedrock-samples
 * package into resources/bedrock/loot_tables.json, minified: the tables the entities name,
 * and any other table those name. Tables are keyed by their path without "loot_tables/" and
 * ".json", and so are the "loot_table" entries that name another table.
 * Usage: php tools/loot-tables/compile.php
 */

namespace IvanCraft623\MobPlugin\tools\loottables\compile;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../bedrock/BedrockSamples.php";

use IvanCraft623\MobPlugin\tools\bedrock\BedrockSamples;
use JsonException;
use RuntimeException;
use stdClass;
use function array_key_first;
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
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error_msg;
use function ksort;
use function printf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;
use const STDERR;

const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

const PATH_PREFIX = "loot_tables/";

const PATH_SUFFIX = ".json";

function main() : int{
	$samples = BedrockSamples::getInstallPath();
	$source = $samples . "/" . BedrockSamples::LOOT_TABLES_PATH;
	$tables = [];
	$missing = [];
	try{
		$roots = findEntityTables($samples . "/" . BedrockSamples::ENTITIES_PATH);
		/** @phpstan-var array<string, true> table name => true */
		$references = $roots;
		while(count($references) !== 0){
			$name = array_key_first($references);
			unset($references[$name]);
			if(isset($tables[$name])){
				continue;
			}
			$file = $source . "/" . $name . PATH_SUFFIX;
			if(!is_file($file)){
				// the entity files name a few tables the samples don't have; one table naming a missing one is an error
				if(!isset($roots[$name])){
					throw new RuntimeException("Loot table \"$name\" is named by another table but is not in the samples");
				}
				$missing[] = $name;
				continue;
			}
			$tables[$name] = readTable($file, $references);
		}
	}catch(RuntimeException $e){
		return fail($e->getMessage());
	}
	ksort($tables, SORT_STRING);

	$contents = json_encode($tables, JSON_FLAGS);
	if($contents === false){
		return fail("Failed to encode the merged loot tables: " . json_last_error_msg());
	}
	$contents .= "\n";

	$path = dirname(__DIR__, 2) . "/resources/bedrock/loot_tables.json";
	if(file_put_contents($path, $contents) === false){
		return fail("Failed to write: $path");
	}

	printf("Merged %d loot table(s) from %s\n", count($tables), $source);
	if(count($missing) !== 0){
		printf("Skipped %d table(s) the entities name that the samples don't have: %s\n", count($missing), implode(", ", $missing));
	}
	printf("Wrote %s (%d bytes)\n", $path, strlen($contents));

	return 0;
}

/**
 * The tables named by the entity files.
 *
 * @phpstan-return array<string, true> table name => true
 */
function findEntityTables(string $entitiesPath) : array{
	$names = [];
	foreach(glob($entitiesPath . "/*.json") ?: [] as $file){
		$raw = file_get_contents($file);
		if($raw === false){
			throw new RuntimeException("Cannot read file: $file");
		}
		try{
			// entity files have comments
			collectTableNames(json_decode(BedrockSamples::stripJsonComments($raw), false, 512, JSON_THROW_ON_ERROR), $names);
		}catch(JsonException $e){
			throw new RuntimeException("Invalid JSON in \"$file\": " . $e->getMessage());
		}
	}
	ksort($names, SORT_STRING);

	return $names;
}

/**
 * @phpstan-param array<string, true> $names
 */
function collectTableNames(mixed $node, array &$names) : void{
	if(is_string($node)){
		if(str_starts_with($node, PATH_PREFIX)){
			$names[tableName($node)] = true;
		}
	}elseif(is_array($node)){
		foreach($node as $child){
			collectTableNames($child, $names);
		}
	}elseif($node instanceof stdClass){
		foreach(get_object_vars($node) as $child){
			collectTableNames($child, $names);
		}
	}
}

/**
 * "loot_tables/entities/cow.json" is "entities/cow"; the data writes the path both ways.
 */
function tableName(string $path) : string{
	if(str_starts_with($path, PATH_PREFIX)){
		$path = substr($path, strlen(PATH_PREFIX));
	}
	if(str_ends_with($path, PATH_SUFFIX)){
		$path = substr($path, 0, -strlen(PATH_SUFFIX));
	}

	return $path;
}

/**
 * Objects (not assoc arrays) are decoded on purpose: an empty JSON object must stay {}
 * instead of becoming [].
 *
 * @phpstan-param array<string, true> $references receives the names of the tables it names
 */
function readTable(string $file, array &$references) : stdClass{
	$raw = file_get_contents($file);
	if($raw === false){
		throw new RuntimeException("Cannot read file: $file");
	}
	try{
		$table = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
	}catch(JsonException $e){
		throw new RuntimeException("Invalid JSON in \"$file\": " . $e->getMessage());
	}
	if(!$table instanceof stdClass){
		throw new RuntimeException("Root of \"$file\" should be a JSON object.");
	}
	normalizeReferences($table, $references);

	return $table;
}

/**
 * Renames the tables named by "loot_table" entries to the keys the tables are stored under.
 *
 * @phpstan-param array<string, true> $references
 */
function normalizeReferences(mixed $node, array &$references) : void{
	if(is_array($node)){
		foreach($node as $child){
			normalizeReferences($child, $references);
		}
	}elseif($node instanceof stdClass){
		$name = $node->name ?? null;
		if(($node->type ?? null) === "loot_table" && is_string($name)){
			$node->name = $name = tableName($name);
			$references[$name] = true;
		}
		foreach(get_object_vars($node) as $child){
			normalizeReferences($child, $references);
		}
	}
}

function fail(string $message) : int{
	fwrite(STDERR, basename(__FILE__) . ": $message\n");

	return 1;
}

exit(main());
