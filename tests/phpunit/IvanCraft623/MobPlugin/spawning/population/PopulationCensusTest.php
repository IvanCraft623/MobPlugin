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

namespace IvanCraft623\MobPlugin\spawning\population;

use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaMobCategories;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\spawner\GroundLevelCache;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;
use function count;

/**
 * Wrong counts break the caps: spawning then runs unbounded or stops.
 */
final class PopulationCensusTest extends TestCase{
	private const MONSTER = "test:monster";
	private const FISH = "test:fish";

	private SpawnRuleRegistry $registry;

	private EntitySpawnBands $bands;

	/** @phpstan-var array<int, list<Entity>> chunk hash => entities */
	private array $entities = [];

	protected function setUp() : void{
		$this->registry = new SpawnRuleRegistry();
		foreach([[self::FISH, VanillaMobCategories::WATER_ANIMAL], [self::MONSTER, VanillaMobCategories::MONSTER]] as [$identifier, $categoryId]){
			$this->registry->register(new SpawnRules($identifier, $categoryId, [new SpawnRuleGroup([])], static fn(World $world, Vector3 $pos, SpawnRuleGroup $group) : Entity => throw new \LogicException("not spawned in this test"), new EntitySizeInfo(1.0, 1.0)));
		}
		$this->bands = new EntitySpawnBands();
		$this->entities = [];
	}

	public function testCountsByBandCategoryAndIdentifier() : void{
		$this->place(self::MONSTER, 0, 0, SpawnBand::SURFACE);
		$this->place(self::MONSTER, 1, 0, SpawnBand::CAVE);
		$this->place(self::MONSTER, 1, 0, SpawnBand::CAVE);
		$this->place(self::FISH, 0, 1, SpawnBand::SURFACE);

		$counts = $this->census()->getRegionPopulation(0, 0);

		self::assertSame(1, $counts->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::SURFACE));
		self::assertSame(2, $counts->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE));
		self::assertSame(2, $counts->getIdentifierCount(self::MONSTER, SpawnBand::CAVE));
		self::assertSame(1, $counts->getCategoryCount(VanillaMobCategories::WATER_ANIMAL, SpawnBand::SURFACE));
		self::assertSame(0, $counts->getIdentifierCount(self::FISH, SpawnBand::CAVE));
	}

	/**
	 * Mobs spawned during a pass count against the caps of the rest of the pass.
	 */
	public function testAddUpdatesTheRegionsThatCoverTheChunk() : void{
		$census = $this->census();
		$near = $census->getRegionPopulation(0, 0);
		$far = $census->getRegionPopulation(20, 20);

		$spawned = $this->place(self::MONSTER, 3, 3, SpawnBand::CAVE);
		$census->add($spawned);

		self::assertSame(1, $near->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE), "the region already handed out sees it");
		self::assertSame(0, $far->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE));
		// A region summed afterwards reuses the chunk's updated counts.
		self::assertSame(1, $census->getRegionPopulation(1, 1)->getIdentifierCount(self::MONSTER, SpawnBand::CAVE));
	}

	private function place(string $identifier, int $chunkX, int $chunkZ, SpawnBand $band) : Entity{
		$index = count($this->entities[World::chunkHash($chunkX, $chunkZ)] ?? []);
		$position = new Position(($chunkX << 4) + $index + 0.5, 64.0, ($chunkZ << 4) + 0.5, null);
		$entity = match($identifier){
			self::MONSTER => new class($position) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return "test:monster";
				}
			},
			default => new class($position) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return "test:fish";
				}
			},
		};
		$this->bands->set($entity, $band);
		$this->entities[World::chunkHash($chunkX, $chunkZ)][] = $entity;

		return $entity;
	}

	private function census() : PopulationCensus{
		$world = $this->createMock(World::class);
		$world->method("getChunkEntities")->willReturnCallback(fn(int $chunkX, int $chunkZ) : array => $this->entities[World::chunkHash($chunkX, $chunkZ)] ?? []);

		return new PopulationCensus($world, new GroundLevelCache($world), $this->bands, $this->registry);
	}
}
