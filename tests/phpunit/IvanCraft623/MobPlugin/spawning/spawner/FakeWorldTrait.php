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

use PHPUnit\Framework\MockObject\MockObject;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\world\World;
use function max;

/**
 * A mocked World over editable columns (stone up to the ground, then water, then a leaf
 * canopy) and per-chunk entity lists.
 */
trait FakeWorldTrait{
	private int $defaultGroundY = 63;

	/** @phpstan-var array<string, int> "x:z" => ground Y */
	private array $groundY = [];

	/** @phpstan-var array<string, int> "x:z" => top water Y */
	private array $waterTopY = [];

	/** @phpstan-var array<string, int> "x:z" => canopy Y */
	private array $canopyY = [];

	/** @phpstan-var array<int, list<Entity>> */
	private array $chunkEntities = [];

	private int $blockReads = 0;

	private function createWorld() : World&MockObject{
		$world = $this->createMock(World::class);
		$world->method("getMinY")->willReturn(0);
		$world->method("getMaxY")->willReturn(256);
		$world->method("getHighestBlockAt")->willReturnCallback(fn(int $x, int $z) : int => max(
			$this->groundY["$x:$z"] ?? $this->defaultGroundY,
			$this->waterTopY["$x:$z"] ?? 0,
			$this->canopyY["$x:$z"] ?? 0
		));
		$world->method("getBlockAt")->willReturnCallback(function(int $x, int $y, int $z) : Block{
			$this->blockReads++;
			if($y <= ($this->groundY["$x:$z"] ?? $this->defaultGroundY)){
				return VanillaBlocks::STONE();
			}
			if($y <= ($this->waterTopY["$x:$z"] ?? 0)){
				return VanillaBlocks::WATER();
			}
			if($y === ($this->canopyY["$x:$z"] ?? null)){
				return VanillaBlocks::OAK_LEAVES();
			}

			return VanillaBlocks::AIR();
		});
		$world->method("getChunkEntities")->willReturnCallback(fn(int $chunkX, int $chunkZ) : array => $this->chunkEntities[World::chunkHash($chunkX, $chunkZ)] ?? []);

		return $world;
	}

	/**
	 * @phpstan-param class-string<Entity> $class
	 */
	private function addEntity(string $class, float $x, float $y, float $z) : Entity{
		$entity = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Entity::class, "location"))->setValue($entity, new Location($x, $y, $z, null, 0.0, 0.0));
		// Never constructed, so it must not run close() (and its server events) on destruct.
		(new \ReflectionProperty(Entity::class, "closeInFlight"))->setValue($entity, true);
		$this->chunkEntities[World::chunkHash(((int) $x) >> 4, ((int) $z) >> 4)][] = $entity;

		return $entity;
	}
}
