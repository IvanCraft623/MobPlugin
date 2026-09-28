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

use PHPUnit\Framework\TestCase;

use function array_diff;

/**
 * Guards the mob-category registry: the vanilla categories a squid's spawn rules
 * reference resolve with the expected caps (the root of the unbounded-squid bug), and
 * plugin consumers can register custom categories.
 */
final class MobCategoryRegistryTest extends TestCase{

	public function testVanillaAnimalCategoryCapsSquidLikeSurfacePopulation() : void{
		$animal = MobCategoryRegistry::getInstance()->get("animal");
		self::assertNotNull($animal, "vanilla 'animal' category must be registered");
		// Squid species use "population_control": "animal" — the cap that was being
		// bypassed because raw-PMMP squids never entered the census.
		self::assertSame(4, $animal->getPopulationCaps()->get(SpawnBand::SURFACE));
		self::assertSame(0, $animal->getPopulationCaps()->get(SpawnBand::CAVE));
	}

	public function testAllVanillaCategoriesAreRegistered() : void{
		$registry = MobCategoryRegistry::getInstance();
		$expected = ["monster", "animal", "ambient", "water_animal", "cat", MobCategoryRegistry::CREATURE];
		self::assertSame([], array_diff($expected, $registry->getIds()), "every vanilla category id must be registered");
	}

	public function testCustomCategoryCanBeRegisteredAndLookedUp() : void{
		$registry = MobCategoryRegistry::getInstance();
		$id = "test_custom_" . bin2hex(random_bytes(4));
		try{
			$registry->register(new MobCategory($id, new BandCounts(2, 6), 48));
			$custom = $registry->get($id);
			self::assertNotNull($custom);
			self::assertSame($id, $custom->id);
			self::assertSame(2, $custom->getPopulationCaps()->get(SpawnBand::SURFACE));
			self::assertSame(6, $custom->getPopulationCaps()->get(SpawnBand::CAVE));
			self::assertSame(48, $custom->getDespawnDistance());
		}finally{
			// leave the registry in its original shape for other tests
			$registry->unregister($id);
		}
	}

	public function testSpeciesPopulationControlResolvesToAnimal() : void{
		// A squid's parsed rule carries category id "animal"; the registry resolves it to
		// the same category object the census aggregates under.
		$category = MobCategoryRegistry::getInstance()->get("animal");
		self::assertNotNull($category);
		self::assertSame("animal", $category->id);
	}
}