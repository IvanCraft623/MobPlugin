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

use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use PHPUnit\Framework\TestCase;
use function dirname;

/**
 * The bundled rules must load: a failure disables the plugin at startup.
 */
final class SpawnRulesParseableTest extends TestCase{
	public function testBundledRulesLoadIntoRegisteredCategories() : void{
		// Throws on anything the strict loader can't compile.
		BiomeTagMap::setInstance(new BiomeTagMap([]));
		$parsed = SpawnRulesParser::createVanilla()->parseFile(dirname(__DIR__, 5) . "/resources/spawning/spawn_rules.json");

		self::assertNotEmpty($parsed);
		foreach($parsed as $identifier => [$categoryId]){
			// registerVanilla() throws at startup for a rule whose category isn't registered.
			self::assertTrue(MobCategoryRegistry::getInstance()->has($categoryId), "$identifier uses unregistered category \"$categoryId\"");
		}
	}
}
