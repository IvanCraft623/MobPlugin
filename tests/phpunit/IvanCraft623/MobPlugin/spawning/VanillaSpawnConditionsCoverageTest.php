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

use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaSpawnConditions;
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesFactory;
use PHPUnit\Framework\TestCase;
use function implode;

/**
 * Every declared VanillaSpawnConditions constant must have a parser registered (or be
 * explicitly unsupported / pass-through), so a schema change surfaces here, not as a
 * silently-never-spawned condition.
 */
final class VanillaSpawnConditionsCoverageTest extends TestCase{

	public function testEverySchemaConditionIsRegistered() : void{
		$registry = SpawnRulesFactory::createDefault()->getConditionRegistry();

		$missing = [];
		foreach(VanillaSpawnConditions::getAll() as $condition){
			if($registry->get($condition) === null){
				$missing[] = $condition;
			}
		}
		self::assertSame([], $missing, "Spawn conditions not classified by SpawnConditionRegistry::getInstance()'s default registrations: " . implode(", ", $missing));
	}

	public function testSchemaVersionIsPinned() : void{
		// The artifact pins the official schema version it was generated from; keep it
		// explicit so a schema change forces a conscious version bump.
		self::assertSame("1.21.50", SpawnSchema::SCHEMA_VERSION);
	}
}
