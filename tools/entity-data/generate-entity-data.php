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
 * Generates EntityIds and VanillaEntitySizes in src/IvanCraft623/MobPlugin/data/bedrock/
 * from the entity files of the pinned mojang/bedrock-samples package. CI regenerates and
 * fails on any diff. Usage: php tools/entity-data/generate-entity-data.php
 */

namespace IvanCraft623\MobPlugin\tools\entitydata;

require __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../spawn-rules/BedrockSamples.php";

use IvanCraft623\MobPlugin\tools\spawnrules\BedrockSamples;
use JsonException;
use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function glob;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function ksort;
use function preg_match;
use function printf;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;
use function var_export;
use const JSON_THROW_ON_ERROR;
use const SORT_STRING;
use const STDERR;

const IDENTIFIER_PREFIX = "minecraft:";
const OUTPUT_NAMESPACE = "IvanCraft623\\MobPlugin\\data\\bedrock";

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

function main() : int{
	try{
		$entities = readEntities(BedrockSamples::getInstallPath() . "/" . BedrockSamples::ENTITIES_PATH);
	}catch(\RuntimeException $e){
		return fail($e->getMessage());
	}

	$outDir = dirname(__DIR__, 2) . "/src/IvanCraft623/MobPlugin/data/bedrock";
	$artifacts = [
		$outDir . "/EntityIds.php" => buildEntityIdsArtifact($entities),
		$outDir . "/VanillaEntitySizes.php" => buildEntitySizesArtifact($entities),
	];
	foreach($artifacts as $path => $contents){
		if(file_put_contents($path, $contents) === false){
			return fail("Failed to write: $path");
		}
		printf("Wrote %s (%d bytes)\n", $path, strlen($contents));
	}
	$sized = 0;
	foreach($entities as [, $size]){
		$sized += $size !== null ? 1 : 0;
	}
	printf("Generated %d entity id(s), %d with a base collision box\n", count($entities), $sized);

	return 0;
}

function fail(string $message) : int{
	fwrite(STDERR, "ERROR: $message\n");

	return 1;
}

/**
 * Every entity of the samples, keyed and sorted by its constant name. Only the base
 * collision box is read: component groups hold variants (babies, slime sizes).
 *
 * @phpstan-return array<string, array{string, array{float, float}|null}> constant name => [identifier, [width, height] or null]
 */
function readEntities(string $entitiesDir) : array{
	$files = glob($entitiesDir . "/*.json");
	if($files === false || count($files) === 0){
		throw new \RuntimeException("No entity files found in $entitiesDir");
	}

	$entities = [];
	foreach($files as $file){
		$raw = file_get_contents($file);
		if($raw === false){
			throw new \RuntimeException("Cannot read file: $file");
		}
		try{
			$decoded = json_decode(BedrockSamples::stripJsonComments($raw), true, 512, JSON_THROW_ON_ERROR);
		}catch(JsonException $e){
			throw new \RuntimeException("Invalid JSON in \"$file\": " . $e->getMessage());
		}
		$entity = is_array($decoded) ? ($decoded["minecraft:entity"] ?? null) : null;
		if(!is_array($entity)){
			throw new \RuntimeException("Missing \"minecraft:entity\" object in \"$file\"");
		}
		$description = $entity["description"] ?? null;
		$identifier = is_array($description) ? ($description["identifier"] ?? null) : null;
		if(!is_string($identifier) || !str_starts_with($identifier, IDENTIFIER_PREFIX)){
			throw new \RuntimeException("Missing or invalid \"description.identifier\" in \"$file\"");
		}
		// The rule PocketMine-MP names its own EntityIds constants by.
		$constant = strtoupper(substr($identifier, strlen(IDENTIFIER_PREFIX)));
		if(preg_match('/^[A-Z][A-Z0-9_]*$/', $constant) !== 1){
			throw new \RuntimeException("\"$identifier\" in \"$file\" doesn't make a constant name");
		}
		if(isset($entities[$constant])){
			throw new \RuntimeException("\"$identifier\" in \"" . basename($file) . "\" is declared twice");
		}

		$components = $entity["components"] ?? [];
		$box = is_array($components) ? ($components["minecraft:collision_box"] ?? null) : null;
		$entities[$constant] = [$identifier, $box !== null ? readCollisionBox($box, $file) : null];
	}
	ksort($entities, SORT_STRING);

	return $entities;
}

/**
 * @phpstan-return array{float, float} width and height
 */
function readCollisionBox(mixed $box, string $file) : array{
	$width = is_array($box) ? ($box["width"] ?? null) : null;
	$height = is_array($box) ? ($box["height"] ?? null) : null;
	if(!(is_int($width) || is_float($width)) || !(is_int($height) || is_float($height)) || $width <= 0 || $height <= 0){
		throw new \RuntimeException("Invalid \"minecraft:collision_box\" in \"$file\": width and height must be positive numbers");
	}

	return [(float) $width, (float) $height];
}

function phpFloat(float $value) : string{
	return var_export($value, true);
}

/**
 * @phpstan-param array<string, array{string, array{float, float}|null}> $entities
 */
function buildEntityIdsArtifact(array $entities) : string{
	$lines = [];
	foreach($entities as $constant => [$identifier]){
		$lines[] = sprintf("\tpublic const %s = \"%s\";", $constant, $identifier);
	}

	return FILE_HEADER . sprintf(<<<'PHP'

namespace %s;

/**
 * Auto-generated from the entities of the pinned Mojang bedrock-samples — do not edit by
 * hand. Constants are named as PocketMine-MP names its own.
 *
 * These ids name the entities of the vanilla data. An entity's network id
 * (getNetworkTypeId()) comes from PocketMine-MP's EntityIds, which follows the protocol
 * it speaks.
 *
 * Regenerate with: php tools/entity-data/generate-entity-data.php
 */
final class EntityIds{

%s

	private function __construct(){}
}

PHP, OUTPUT_NAMESPACE, implode("\n", $lines));
}

/**
 * @phpstan-param array<string, array{string, array{float, float}|null}> $entities
 */
function buildEntitySizesArtifact(array $entities) : string{
	$constantLines = [];
	$mapLines = [];
	foreach($entities as $constant => [, $size]){
		if($size === null){
			continue; // only variants declare a box (babies, adults)
		}
		[$width, $height] = $size;
		$constantLines[] = sprintf("\tpublic const %s_WIDTH = %s;", $constant, phpFloat($width));
		$constantLines[] = sprintf("\tpublic const %s_HEIGHT = %s;", $constant, phpFloat($height));
		$mapLines[] = sprintf("\t\tEntityIds::%1\$s => [self::%1\$s_WIDTH, self::%1\$s_HEIGHT],", $constant);
	}

	return FILE_HEADER . sprintf(<<<'PHP'

namespace %s;

use pocketmine\entity\EntitySizeInfo;

/**
 * Auto-generated from the entities of the pinned Mojang bedrock-samples — do not edit by
 * hand. Each entity's base collision box, before scale; entities that only declare boxes
 * for their variants are left out.
 *
 * Regenerate with: php tools/entity-data/generate-entity-data.php
 */
final class VanillaEntitySizes{

%s

	/** @phpstan-var array<string, array{float, float}> entity id => [width, height] */
	private const BY_ID = [
%s
	];

	private function __construct(){}

	/**
	 * The entity's base collision box, with PocketMine-MP's default eye height.
	 */
	public static function get(string $entityId) : ?EntitySizeInfo{
		if(!isset(self::BY_ID[$entityId])){
			return null;
		}
		[$width, $height] = self::BY_ID[$entityId];

		return new EntitySizeInfo($height, $width);
	}
}

PHP, OUTPUT_NAMESPACE, implode("\n", $constantLines), implode("\n", $mapLines));
}

exit(main());
