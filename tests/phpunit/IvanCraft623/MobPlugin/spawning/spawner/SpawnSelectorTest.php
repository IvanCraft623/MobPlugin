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

use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\MobCategory;
use IvanCraft623\MobPlugin\spawning\MobCategoryRegistry;
use IvanCraft623\MobPlugin\spawning\population\PopulationCounts;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\utils\Random;
use function array_map;

/**
 * The caps and density limits: without them spawning runs unbounded.
 */
final class SpawnSelectorTest extends TestCase{
	/** Category id => surface and cave cap. */
	private const CATEGORIES = ["free" => 100, "full" => 2, "m" => 4];

	protected function setUp() : void{
		foreach(self::CATEGORIES as $id => $cap){
			MobCategoryRegistry::getInstance()->register(new MobCategory($id, $cap, $cap, 64));
		}
	}

	protected function tearDown() : void{
		foreach(self::CATEGORIES as $id => $_){
			MobCategoryRegistry::getInstance()->unregister($id);
		}
	}

	public function testCappedCategoryDoesNotCompete() : void{
		$candidates = self::candidates([
			self::rules("minecraft:capped", "full", 1000),
			self::rules("minecraft:free", "free", 1),
		]);
		$ctx = new StubContext(population: PopulationCounts::of([SpawnBand::SURFACE->value => ["full" => 2]]));

		self::assertSame("minecraft:free", self::selector()->select($ctx, $candidates)?->rules->getIdentifier());
	}

	public function testRoomIsWhatTheCategoryCapLeaves() : void{
		$candidates = self::candidates([self::rules("minecraft:mob", "m", 1)]);
		$ctx = new StubContext(population: PopulationCounts::of([SpawnBand::SURFACE->value => ["m" => 3]]));

		self::assertSame(1, self::selector()->select($ctx, $candidates)?->room); // 4 - 3
	}

	public function testDensityLimitTrimsTheRoom() : void{
		$candidates = self::candidates([new SpawnRules("minecraft:mob", "free", [new SpawnRuleGroup([], surfaceDensityLimit: 5)], self::factory())]);

		$under = new StubContext(population: PopulationCounts::of([], [SpawnBand::SURFACE->value => ["minecraft:mob" => 3]]));
		self::assertSame(2, self::selector()->select($under, $candidates)?->room); // 5 - 3, well under the category's 100

		$atLimit = new StubContext(population: PopulationCounts::of([], [SpawnBand::SURFACE->value => ["minecraft:mob" => 5]]));
		self::assertNull(self::selector()->select($atLimit, $candidates));
	}

	private static function selector() : SpawnSelector{
		return new SpawnSelector(new Random(1), MobCategoryRegistry::getInstance());
	}

	private static function rules(string $identifier, string $categoryId, int $weight) : SpawnRules{
		return new SpawnRules($identifier, $categoryId, [new SpawnRuleGroup([], $weight)], self::factory());
	}

	/**
	 * @phpstan-return \Closure() : Entity
	 */
	private static function factory() : \Closure{
		return static fn() : Entity => throw new \LogicException("the selector never spawns");
	}

	/**
	 * Every condition left as a residual, so the selector does all the matching.
	 *
	 * @phpstan-param list<SpawnRules> $rules
	 * @phpstan-return list<CandidateRule>
	 */
	private static function candidates(array $rules) : array{
		return array_map(static fn(SpawnRules $r) : CandidateRule => new CandidateRule(
			$r,
			array_map(static fn(SpawnRuleGroup $group) : array => [$group, $group->getConditions()], $r->getGroups())
		), $rules);
	}
}
