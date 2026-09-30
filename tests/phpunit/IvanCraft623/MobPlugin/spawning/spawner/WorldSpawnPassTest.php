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

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\spawning\condition\DensityLimitCondition;
use IvanCraft623\MobPlugin\spawning\MobCategory;
use IvanCraft623\MobPlugin\spawning\MobCategoryRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function abs;
use function count;
use function sqrt;

final class WorldSpawnPassTest extends TestCase{
	use FakeWorldTrait;

	private const CATEGORY = "test_pass";

	private const ATTEMPTS = 300;

	/** @phpstan-var list<Vector3> */
	private array $spawned = [];

	protected function setUp() : void{
		CustomTimings::init();
		MobCategoryRegistry::getInstance()->register(new MobCategory(self::CATEGORY, 1000, 1000, 64));
	}

	protected function tearDown() : void{
		SpawnRuleRegistry::getInstance()->unregister(CensusTestCow::getNetworkTypeId());
		MobCategoryRegistry::getInstance()->unregister(self::CATEGORY);
	}

	public function testRingOffsetStaysInsideTheRing() : void{
		$random = new Random(42);
		$inner = 0;
		for($i = 0; $i < 10000; $i++){
			[$dx, $dz] = WorldSpawnPass::getRingOffset($random);
			$distance = sqrt($dx * $dx + $dz * $dz);
			self::assertGreaterThanOrEqual(WorldSpawnPass::MIN_PLAYER_DISTANCE - 1e-9, $distance);
			self::assertLessThanOrEqual(WorldSpawnPass::MAX_PLAYER_DISTANCE + 1e-9, $distance);
			if($distance < 34){
				$inner++;
			}
		}
		// Uniform over area: the 24-34 band holds (34² - 24²) / (44² - 24²) ≈ 42.5% of it.
		self::assertEqualsWithDelta(0.425, $inner / 10000, 0.02);
	}

	public function testNextAttemptCountsTheHerdJustSpawned() : void{
		$world = $this->createPassWorld();
		// At most one cow may start from a region that already has one.
		$pass = $this->createPass($world, [new DensityLimitCondition(CensusTestCow::getNetworkTypeId(), 1, null)], function(Vector3 $pos) : void{
			$this->addEntity(CensusTestCow::class, $pos->x, $pos->y, $pos->z);
		});

		for($i = 0; $i < self::ATTEMPTS; $i++){
			$pass->attempt(new Vector3(0, 64, 0));
		}
		self::assertNotEmpty($this->spawned);
		foreach($this->spawned as $i => $pos){
			for($j = 0; $j < $i; $j++){
				$earlier = $this->spawned[$j];
				$inRegion = abs(((int) $pos->x >> 4) - ((int) $earlier->x >> 4)) <= PopulationCensus::REGION_RADIUS
					&& abs(((int) $pos->z >> 4) - ((int) $earlier->z >> 4)) <= PopulationCensus::REGION_RADIUS;
				self::assertFalse($inRegion, "spawn $i started inside the region of spawn $j");
			}
		}
	}

	public function testNextAttemptSeesTheWorldAFactoryChanged() : void{
		$world = $this->createPassWorld();
		$pass = $this->createPass($world, [], function() : void{
			$this->defaultGroundY = 70; // e.g. a factory building under every column
		});

		for($i = 0; $i < self::ATTEMPTS; $i++){
			$pass->attempt(new Vector3(0, 64, 0));
		}
		self::assertGreaterThan(1, count($this->spawned));
		self::assertEquals(64, $this->spawned[0]->y);
		foreach($this->spawned as $i => $pos){
			if($i > 0){
				self::assertEquals(71, $pos->y, "spawn $i stands on the new ground");
			}
		}
	}

	private function createPassWorld() : World&MockObject{
		$world = $this->createWorld();
		$chunk = new Chunk([], true);
		$chunk->setLightPopulated();
		$world->method("getChunk")->willReturn($chunk);
		$world->method("isChunkLoaded")->willReturn(true);
		$world->method("getPlayers")->willReturn([]);
		$world->method("getFullLightAt")->willReturn(15);
		$world->method("getBiomeId")->willReturn(1);
		$world->method("getDifficulty")->willReturn(World::DIFFICULTY_NORMAL);

		return $world;
	}

	/**
	 * @phpstan-param list<DensityLimitCondition> $conditions
	 * @phpstan-param \Closure(Vector3) : void $onSpawn runs inside the factory
	 */
	private function createPass(World $world, array $conditions, \Closure $onSpawn) : WorldSpawnPass{
		$registry = SpawnRuleRegistry::getInstance();
		$registry->register(new SpawnRules(
			CensusTestCow::getNetworkTypeId(),
			self::CATEGORY,
			[new SpawnRuleGroup($conditions)],
			function(World $world, Vector3 $pos) use ($onSpawn) : Entity{
				$this->spawned[] = $pos;
				$onSpawn($pos);

				return SpawnedEntityStub::create();
			}
		));
		$random = new Random(99);

		return new WorldSpawnPass(
			$world,
			new CandidateCache([$registry->get(CensusTestCow::getNetworkTypeId()) ?? throw new \LogicException()]),
			new SpawnSelector($random, MobCategoryRegistry::getInstance()),
			new HerdSpawner($registry, $random),
			$registry,
			$random
		);
	}
}
