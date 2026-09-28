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

use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesFactory;
use PHPUnit\Framework\TestCase;
use function array_keys;
use function dirname;
use function is_string;

/**
 * The loader is strict: the bundled resource must compile with zero unhandled
 * degradations — a parser regression or PM block removal fails loudly here.
 */
final class SpawnRulesParseableTest extends TestCase{
	/**
	 * Entries skipped by design: vanilla spawns these populations through events
	 * (patrols/raids), not natural spawning, so their rule sets are never compiled.
	 */
	private const BY_DESIGN_SKIPPED_ENTRIES = [
		"minecraft:pillager" => true,
		"minecraft:pillager_patrol" => true,
	];

	public function testBundledResourceCompilesStrictly() : void{
		$factory = SpawnRulesFactory::createDefault();
		$rules = $factory->loadFile(dirname(__DIR__, 5) . "/resources/spawning/spawn_rules.json");

		self::assertNotEmpty($rules);
		foreach(array_keys($rules) as $identifier){
			self::assertStringStartsWith("minecraft:", is_string($identifier) ? $identifier : (string) $identifier);
			self::assertArrayNotHasKey($identifier, self::BY_DESIGN_SKIPPED_ENTRIES);
		}
		self::assertArrayHasKey("minecraft:goat", $rules, "goat must load — its only missing block name (powder_snow) is a documented, by-design drop");
	}
}
