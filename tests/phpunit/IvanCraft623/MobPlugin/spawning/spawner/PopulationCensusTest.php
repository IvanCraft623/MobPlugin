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

namespace IvanCraft623\MobPlugin\spawning\spawner;

use IvanCraft623\MobPlugin\spawning\MobCategoryRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\world\World;

final class PopulationCensusTest extends TestCase{
	use FakeWorldTrait;

	private World $world;

	private PopulationCensus $census;

	protected function setUp() : void{
		$registry = SpawnRuleRegistry::getInstance();
		$registry->register(self::rules(CensusTestCow::getNetworkTypeId(), MobCategoryRegistry::ANIMAL));
		$registry->register(self::rules(CensusTestZombie::getNetworkTypeId(), MobCategoryRegistry::MONSTER));
		$this->world = $this->createWorld();
		$this->census = new PopulationCensus(new SpawnPlacement($this->world), $registry);
	}

	protected function tearDown() : void{
		SpawnRuleRegistry::getInstance()->unregister(CensusTestCow::getNetworkTypeId());
		SpawnRuleRegistry::getInstance()->unregister(CensusTestZombie::getNetworkTypeId());
	}

	public function testRegionIsTheNineByNineChunkGrid() : void{
		$this->addEntity(CensusTestCow::class, 8, 64, 8);
		$this->addEntity(CensusTestCow::class, 4 * 16 + 15, 64, 8);
		$this->addEntity(CensusTestCow::class, -4 * 16, 64, -4 * 16 + 3);
		$this->addEntity(CensusTestCow::class, 5 * 16, 64, 8); // one chunk too far
		$this->addEntity(CensusTestCow::class, 8, 64, -5 * 16 + 15);

		$region = $this->census->getRegionPopulation(0, 0);
		self::assertSame(3, $region->getCategoryCount(MobCategoryRegistry::ANIMAL, SpawnBand::SURFACE));
		self::assertSame(3, $region->getIdentifierCount(CensusTestCow::getNetworkTypeId(), SpawnBand::SURFACE));
		// Chunks -3..5 × -5..3: drops the (-4, -4) cow, adds the (5, 0) and (0, -5) ones.
		self::assertSame(4, $this->census->getRegionPopulation(1, -1)->getCategoryCount(MobCategoryRegistry::ANIMAL, SpawnBand::SURFACE));
	}

	public function testCountsByBandCategoryAndIdentifier() : void{
		$this->addEntity(CensusTestCow::class, 1, 64, 1);
		$this->addEntity(CensusTestZombie::class, 2, 64, 2);
		$this->addEntity(CensusTestZombie::class, 3, 20, 3); // below the ground: cave
		$this->addEntity(CensusTestUnknown::class, 4, 64, 4); // no spawn rules
		$closed = $this->addEntity(CensusTestZombie::class, 5, 64, 5);
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($closed, true);

		$region = $this->census->getRegionPopulation(0, 0);
		self::assertSame(1, $region->getCategoryCount(MobCategoryRegistry::ANIMAL, SpawnBand::SURFACE));
		self::assertSame(1, $region->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::SURFACE));
		self::assertSame(1, $region->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::CAVE));
		self::assertSame(1, $region->getIdentifierCount(CensusTestZombie::getNetworkTypeId(), SpawnBand::CAVE));
		self::assertSame(0, $region->getIdentifierCount(CensusTestUnknown::getNetworkTypeId(), SpawnBand::SURFACE));
	}

	public function testRecountAfterClearIncludesNewEntities() : void{
		$this->addEntity(CensusTestCow::class, 1, 64, 1);
		$before = $this->census->getRegionPopulation(0, 0);
		self::assertSame($before, $this->census->getRegionPopulation(0, 0), "regions are memoized");

		$this->addEntity(CensusTestCow::class, 20, 64, 20); // e.g. a herd member
		self::assertSame(1, $this->census->getRegionPopulation(0, 0)->getCategoryCount(MobCategoryRegistry::ANIMAL, SpawnBand::SURFACE));

		$this->census->clear();
		self::assertSame(2, $this->census->getRegionPopulation(0, 0)->getCategoryCount(MobCategoryRegistry::ANIMAL, SpawnBand::SURFACE));
	}

	private static function rules(string $identifier, string $categoryId) : SpawnRules{
		return new SpawnRules($identifier, $categoryId, [], static fn() : Entity => throw new \LogicException("never spawned"));
	}
}
