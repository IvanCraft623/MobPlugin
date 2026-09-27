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

use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnComponent;
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesFactory;
use PHPUnit\Framework\TestCase;
use function implode;

/**
 * Guards the schema-derived artifact inventory against the parser registry.
 *
 * The SpawnComponent enum is generated from the official Mojang spawn schemas. If Mojang
 * later adds, renames or removes a spawn component, the enum changes — and this test
 * surfaces it: every declared component must have a parser registered by the default
 * registry (or be explicitly classified as unsupported / pass-through), otherwise a
 * newly-added component would silently never spawn and a removed one would linger.
 */
final class SpawnComponentCoverageTest extends TestCase{

	public function testEverySchemaComponentIsRegistered() : void{
		$registry = SpawnRulesFactory::createDefault()->getConditionRegistry();

		$missing = [];
		foreach(SpawnComponent::cases() as $component){
			if($registry->get($component) === null){
				$missing[] = $component->value;
			}
		}
		self::assertSame([], $missing, "Spawn components not classified by SpawnConditionRegistry::getInstance()'s default registrations: " . implode(", ", $missing));
	}

	public function testSchemaVersionIsPinned() : void{
		// The artifact pins the official schema version it was generated from; keep it
		// explicit so a schema change forces a conscious version bump.
		self::assertSame("1.21.50", SpawnSchema::SCHEMA_VERSION);
	}
}
