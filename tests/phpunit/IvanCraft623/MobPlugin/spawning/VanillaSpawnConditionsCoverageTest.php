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
use function implode;

/**
 * Every declared VanillaSpawnConditions constant must have a parser registered (or be
 * explicitly unsupported / pass-through), so a schema change surfaces here, not as a
 * silently-never-spawned condition.
 */
final class VanillaSpawnConditionsCoverageTest extends TestCase{

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

	public function testSchemaVersionMatchesComposer() : void{
		// composer.json declares the pinned schema version as the bedrock-samples package
		// version; the generated artifacts must come from that same version.
		self::assertSame(InstalledVersions::getPrettyVersion("mojang/bedrock-samples"), SpawnSchema::SCHEMA_VERSION, "regenerate with php tools/spawn-rules/generate-schema.php");
	}
}
