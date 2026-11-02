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

/**
 * Wrong counts break the caps: spawning then runs unbounded or stops.
 */
final class PopulationCensusTest extends TestCase{
	public const MONSTER = "test:monster";
	public const OTHER_MONSTER = "test:other_monster";
	public const FISH = "test:fish";
	public const UNREGISTERED = "test:unregistered";

	private SpawnRuleRegistry $registry;

	private EntitySpawnBands $bands;

	/** @phpstan-var array<int, list<Entity>> chunk hash => entities */
	private array $entities = [];

	protected function setUp() : void{
		$this->registry = new SpawnRuleRegistry();
		$this->register(self::MONSTER, VanillaMobCategories::MONSTER);
		$this->register(self::OTHER_MONSTER, VanillaMobCategories::MONSTER);
		$this->register(self::FISH, VanillaMobCategories::WATER_ANIMAL);
		$this->bands = new EntitySpawnBands();
	}

	public function testCountsByBandCategoryAndIdentifier() : void{
		$this->place(self::MONSTER, 0, 0, SpawnBand::SURFACE);
		$this->place(self::OTHER_MONSTER, 0, 0, SpawnBand::SURFACE);
		$this->place(self::MONSTER, 1, 0, SpawnBand::CAVE);
		$this->place(self::MONSTER, 1, 0, SpawnBand::CAVE);
		$this->place(self::FISH, 0, 1, SpawnBand::SURFACE);

		$counts = $this->census()->getRegionPopulation(0, 0);

		self::assertSame(2, $counts->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::SURFACE));
		self::assertSame(1, $counts->getIdentifierCount(self::MONSTER, SpawnBand::SURFACE));
		self::assertSame(1, $counts->getIdentifierCount(self::OTHER_MONSTER, SpawnBand::SURFACE));
		self::assertSame(2, $counts->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE));
		self::assertSame(2, $counts->getIdentifierCount(self::MONSTER, SpawnBand::CAVE));
		self::assertSame(0, $counts->getIdentifierCount(self::OTHER_MONSTER, SpawnBand::CAVE));
		self::assertSame(1, $counts->getCategoryCount(VanillaMobCategories::WATER_ANIMAL, SpawnBand::SURFACE));
		self::assertSame(1, $counts->getIdentifierCount(self::FISH, SpawnBand::SURFACE));
		self::assertSame(0, $counts->getCategoryCount(VanillaMobCategories::WATER_ANIMAL, SpawnBand::CAVE));
	}

	public function testRegionCoversFourChunksAround() : void{
		foreach([[4, -4], [-4, 4]] as [$chunkX, $chunkZ]){
			$this->place(self::MONSTER, $chunkX, $chunkZ, SpawnBand::SURFACE);
		}
		foreach([[5, 0], [-5, 0], [0, 5], [0, -5]] as [$chunkX, $chunkZ]){
			$this->place(self::MONSTER, $chunkX, $chunkZ, SpawnBand::SURFACE);
		}

		$counts = $this->census()->getRegionPopulation(0, 0);

		self::assertSame(2, $counts->getIdentifierCount(self::MONSTER, SpawnBand::SURFACE));
	}

	public function testIgnoresClosedAndUnregisteredEntities() : void{
		$closed = $this->place(self::MONSTER, 0, 0, SpawnBand::SURFACE, closed: true);
		$unregistered = $this->place(self::UNREGISTERED, 0, 0, SpawnBand::SURFACE);

		$census = $this->census();
		$counts = $census->getRegionPopulation(0, 0);
		$census->add($closed);
		$census->add($unregistered);

		self::assertSame(0, $counts->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::SURFACE));
		self::assertSame(0, $counts->getIdentifierCount(self::UNREGISTERED, SpawnBand::SURFACE));
	}

	/**
	 * Mobs spawned during a pass count against the caps of the rest of the pass.
	 */
	public function testAddUpdatesTheRegionsThatCoverTheChunk() : void{
		$census = $this->census();
		$near = $census->getRegionPopulation(0, 0);
		$farAlongX = $census->getRegionPopulation(9, 4);
		$farAlongZ = $census->getRegionPopulation(4, 9);

		// On the edge of the near region, one chunk outside the far ones.
		$spawned = $this->place(self::MONSTER, 4, 4, SpawnBand::CAVE);
		$census->add($spawned);

		self::assertSame(1, $near->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE), "the region already handed out sees it");
		self::assertSame(0, $farAlongX->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE));
		self::assertSame(0, $farAlongZ->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE));
		// A region summed afterwards reuses the chunk's updated counts.
		self::assertSame(1, $census->getRegionPopulation(1, 1)->getIdentifierCount(self::MONSTER, SpawnBand::CAVE));
	}

	/**
	 * A chunk counted after the spawn finds the mob in the world: adding it must not
	 * count it a second time.
	 */
	public function testAddBeforeTheChunkIsCountedCountsOnce() : void{
		$census = $this->census();

		$spawned = $this->place(self::MONSTER, 3, 3, SpawnBand::CAVE);
		$census->add($spawned);

		self::assertSame(1, $census->getRegionPopulation(0, 0)->getIdentifierCount(self::MONSTER, SpawnBand::CAVE));
	}

	private function register(string $identifier, string $categoryId) : void{
		$this->registry->register(new SpawnRules(
			$identifier,
			$categoryId,
			[new SpawnRuleGroup([])],
			static fn(World $world, Vector3 $pos, SpawnRuleGroup $group) : Entity => throw new \LogicException("not spawned in this test"),
			new EntitySizeInfo(1.0, 1.0)
		));
	}

	/**
	 * @phpstan-param self::* $identifier
	 */
	private function place(string $identifier, int $chunkX, int $chunkZ, SpawnBand $band, bool $closed = false) : Entity{
		$position = new Position(($chunkX << 4) + 0.5, 64.0, ($chunkZ << 4) + 0.5, null);
		// The census classifies by class, so each identifier needs its own.
		$entity = match($identifier){
			self::MONSTER => new class($position, $closed) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return PopulationCensusTest::MONSTER;
				}
			},
			self::OTHER_MONSTER => new class($position, $closed) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return PopulationCensusTest::OTHER_MONSTER;
				}
			},
			self::FISH => new class($position, $closed) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return PopulationCensusTest::FISH;
				}
			},
			self::UNREGISTERED => new class($position, $closed) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return PopulationCensusTest::UNREGISTERED;
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
