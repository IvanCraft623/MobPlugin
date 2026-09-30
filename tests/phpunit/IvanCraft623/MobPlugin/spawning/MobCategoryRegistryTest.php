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

final class MobCategoryRegistryTest extends TestCase{

	/**
	 * registerVanilla() throws at server start for a rule whose category isn't registered.
	 */
	public function testEveryDataCategoryIsRegistered() : void{
		$parsed = SpawnRulesParser::createVanilla(new BiomeTagMap([]))->parseFile(dirname(__DIR__, 5) . "/resources/spawning/spawn_rules.json");
		self::assertNotEmpty($parsed);

		$registry = MobCategoryRegistry::getInstance();
		foreach($parsed as $identifier => [$categoryId]){
			self::assertTrue($registry->has($categoryId), "$identifier uses unregistered category \"$categoryId\"");
		}
	}
}
