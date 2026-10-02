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

namespace IvanCraft623\MobPlugin\spawning;

use Composer\InstalledVersions;
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaSpawnConditions;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use PHPUnit\Framework\TestCase;
use function array_keys;
use function basename;
use function class_exists;
use function dirname;
use function file_get_contents;
use function glob;
use function implode;
use function json_decode;
use function property_exists;
use function strlen;
use function substr;
use const JSON_THROW_ON_ERROR;

/**
 * Every declared VanillaSpawnConditions constant must have a parser registered (or be
 * explicitly unsupported / pass-through), so a schema change surfaces here, not as a
 * silently-never-spawned condition.
 */
final class VanillaSpawnConditionsCoverageTest extends TestCase{
	/** Schemas of the document and component envelopes, not of a component payload. */
	private const ENVELOPE_SCHEMAS = ["BiomeConditions" => true, "Rules" => true, "Description" => true];

	public function testEverySchemaConditionIsRegistered() : void{
		$parser = SpawnRulesParser::createVanilla(new BiomeTagMap([]));

		$missing = [];
		foreach(VanillaSpawnConditions::getAll() as $condition){
			if($parser->getComponent($condition) === null){
				$missing[] = $condition;
			}
		}
		self::assertSame([], $missing, "Spawn conditions not registered by SpawnRulesParser::createVanilla(new BiomeTagMap([])): " . implode(", ", $missing));
	}

	/**
	 * Every component schema with a payload has its generated model, whether or not a
	 * parser reads it: unimplemented components keep theirs for whoever implements them.
	 */
	public function testEveryPayloadSchemaHasItsModel() : void{
		$schemaDir = InstalledVersions::getInstallPath("mojang/bedrock-samples") . "/metadata/json_schemas/server/spawn/" . SpawnSchema::SCHEMA_VERSION;
		$files = glob($schemaDir . "/Spawn *.json");
		self::assertNotEmpty($files, "no spawn schemas found in $schemaDir");

		$models = 0;
		foreach($files as $file){
			$name = substr(basename($file, ".json"), strlen("Spawn "));
			if(isset(self::ENVELOPE_SCHEMAS[$name])){
				continue;
			}
			$contents = file_get_contents($file);
			self::assertIsString($contents);
			$schema = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
			self::assertIsArray($schema);
			$properties = $schema["properties"] ?? [];
			self::assertIsArray($properties);
			if($properties === []){
				continue; // a marker component carries no payload
			}

			$class = "IvanCraft623\\MobPlugin\\spawning\\parse\\schema\\model\\{$name}Data";
			self::assertTrue(class_exists($class), "$class is missing; regenerate with php tools/spawn-rules/generate-schema.php");
			foreach(array_keys($properties) as $property){
				self::assertTrue(property_exists($class, (string) $property), "$class::\$$property is missing");
			}
			$models++;
		}
		self::assertGreaterThan(0, $models);
		$generated = glob(dirname(__DIR__, 5) . "/src/IvanCraft623/MobPlugin/spawning/parse/schema/model/*Data.php");
		self::assertNotFalse($generated);
		self::assertCount($models, $generated, "a generated model has no payload schema");
	}

	public function testSchemaVersionMatchesComposer() : void{
		// composer.json declares the pinned schema version as the bedrock-samples package
		// version; the generated artifacts must come from that same version.
		self::assertSame(InstalledVersions::getPrettyVersion("mojang/bedrock-samples"), SpawnSchema::SCHEMA_VERSION, "regenerate with php tools/spawn-rules/generate-schema.php");
	}
}
