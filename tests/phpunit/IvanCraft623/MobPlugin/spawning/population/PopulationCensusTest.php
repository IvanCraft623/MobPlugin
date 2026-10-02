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

use IvanCraft623\MobPlugin\spawning\MobCategoryRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\spawner\GroundLevelCache;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;
use function count;

final class PopulationCensusTest extends TestCase{
	private const MONSTER = "test:monster";
	private const FISH = "test:fish";
	private const UNCOUNTED = "test:uncounted";

	private SpawnRuleRegistry $registry;

	private EntitySpawnBands $bands;

	/** @phpstan-var array<int, list<Entity>> chunk hash => entities */
	private array $entities = [];

	protected function setUp() : void{
		$this->registry = new SpawnRuleRegistry();
		foreach([[self::FISH, MobCategoryRegistry::WATER_ANIMAL], [self::MONSTER, MobCategoryRegistry::MONSTER]] as [$identifier, $categoryId]){
			$this->registry->register(new SpawnRules($identifier, $categoryId, [new SpawnRuleGroup([])], static fn(World $world, Vector3 $pos, SpawnRuleGroup $group) : Entity => throw new \LogicException("not spawned in this test")));
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

		self::assertSame(1, $counts->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::SURFACE));
		self::assertSame(2, $counts->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::CAVE));
		self::assertSame(2, $counts->getIdentifierCount(self::MONSTER, SpawnBand::CAVE));
		self::assertSame(1, $counts->getCategoryCount(MobCategoryRegistry::WATER_ANIMAL, SpawnBand::SURFACE));
		self::assertSame(0, $counts->getIdentifierCount(self::FISH, SpawnBand::CAVE));
	}

	public function testClosedEntitiesAndTypesWithoutRulesAreSkipped() : void{
		$this->place(self::MONSTER, 0, 0, SpawnBand::SURFACE, closed: true);
		$this->place(self::UNCOUNTED, 0, 0, SpawnBand::SURFACE);

		$counts = $this->census()->getRegionPopulation(0, 0);

		self::assertSame(0, $counts->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::SURFACE));
		self::assertSame(0, $counts->getIdentifierCount(self::UNCOUNTED, SpawnBand::SURFACE));
	}

	public function testRegionIsTheNineByNineChunksAround() : void{
		$this->place(self::MONSTER, 4, -4, SpawnBand::SURFACE);
		$this->place(self::MONSTER, 5, 0, SpawnBand::SURFACE);
		$this->place(self::MONSTER, 0, -5, SpawnBand::SURFACE);

		self::assertSame(1, $this->census()->getRegionPopulation(0, 0)->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::SURFACE));
	}

	public function testAddUpdatesTheRegionsThatCoverTheChunk() : void{
		$census = $this->census();
		$near = $census->getRegionPopulation(0, 0);
		$far = $census->getRegionPopulation(20, 20);

		$spawned = $this->place(self::MONSTER, 3, 3, SpawnBand::CAVE);
		$census->add($spawned);

		self::assertSame(1, $near->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::CAVE), "the region already handed out sees it");
		self::assertSame(0, $far->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::CAVE));
		// A region summed afterwards reuses the chunk's updated counts.
		self::assertSame(1, $census->getRegionPopulation(1, 1)->getIdentifierCount(self::MONSTER, SpawnBand::CAVE));
	}

	public function testAddBeforeTheChunkIsCountedIsNotCountedTwice() : void{
		$census = $this->census();
		$census->add($this->place(self::MONSTER, 0, 0, SpawnBand::CAVE));

		self::assertSame(1, $census->getRegionPopulation(0, 0)->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::CAVE));
	}

	public function testUnknownBandIsDerivedFromThePositionOnce() : void{
		$above = $this->place(self::MONSTER, 0, 0, null, y: 70.0);
		$below = $this->place(self::MONSTER, 0, 0, null, y: 20.0);

		$world = $this->world();
		$world->expects(self::exactly(2))->method("getHighestBlockAt")->willReturn(64);
		$world->method("getBlockAt")->willReturn(VanillaBlocks::STONE());
		$world->method("getMinY")->willReturn(-64);

		$counts = $this->census($world)->getRegionPopulation(0, 0);
		self::assertSame(1, $counts->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::SURFACE));
		self::assertSame(1, $counts->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::CAVE));
		self::assertSame(SpawnBand::SURFACE, $this->bands->get($above));
		self::assertSame(SpawnBand::CAVE, $this->bands->get($below));

		// A later census finds the bands remembered: the ground is not read again.
		$this->census($world)->getRegionPopulation(0, 0);
	}

	private function place(string $identifier, int $chunkX, int $chunkZ, ?SpawnBand $band, bool $closed = false, float $y = 64.0) : Entity{
		// Columns differ per entity so each one needs its own ground lookup.
		$index = count($this->entities[World::chunkHash($chunkX, $chunkZ)] ?? []);
		$position = new Position(($chunkX << 4) + $index + 0.5, $y, ($chunkZ << 4) + 0.5, null);
		$entity = match($identifier){
			self::MONSTER => new class($position, $closed) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return "test:monster";
				}
			},
			self::FISH => new class($position, $closed) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return "test:fish";
				}
			},
			default => new class($position, $closed) extends FakeEntity{
				public static function getNetworkTypeId() : string{
					return "test:uncounted";
				}
			},
		};
		if($band !== null){
			$this->bands->set($entity, $band);
		}
		$this->entities[World::chunkHash($chunkX, $chunkZ)][] = $entity;

		return $entity;
	}

	private function world() : World&\PHPUnit\Framework\MockObject\MockObject{
		$world = $this->createMock(World::class);
		$world->method("getChunkEntities")->willReturnCallback(fn(int $chunkX, int $chunkZ) : array => $this->entities[World::chunkHash($chunkX, $chunkZ)] ?? []);

		return $world;
	}

	private function census(?World $world = null) : PopulationCensus{
		$world ??= $this->world();

		return new PopulationCensus($world, new GroundLevelCache($world), $this->bands, $this->registry);
	}
}
