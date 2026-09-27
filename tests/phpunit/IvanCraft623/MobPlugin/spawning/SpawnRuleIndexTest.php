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

use IvanCraft623\MobPlugin\entity\MobCategory;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\BrightnessFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\DifficultyFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\HabitatBandCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\HeightFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\SpawnsInLiquid;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\SpawnsOnBlock;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;
use IvanCraft623\MobPlugin\spawning\plan\SpawnRuleIndex;
use pocketmine\block\BlockTypeIds;
use PHPUnit\Framework\TestCase;
use function array_values;
use function count;

/**
 * The planner index is an over-approximation: it must never reject a rule set that the
 * real conditions could match at a given position. This property test asserts exactly
 * that — for a grid of position profiles, every rule set whose conditions evaluate to a
 * match must be offered by SpawnRuleIndex::candidatesFor() (so the index is a superset
 * of brute-force evaluation). Anything the index rejects must therefore provably fail.
 */
final class SpawnRuleIndexTest extends TestCase{

	/** Light level the fixture env reports at every position. */
	private const LIGHT = 10;

	/**
	 * Biome id that carries the "frozen" tag in the fixture resolver.
	 */
	public const FROZEN_BIOME_ID = 400;

	public function testIndexIsSupersetOfBruteForceAcrossPositionGrid() : void{
		$tags = self::tags();

		$rules = [
			"surface_animal" => self::ruleSurface(),
			"cave_monster" => self::ruleCave(),
			"wet_monster" => self::ruleUnderwater(),
			"diurnal" => self::ruleBright(),
			"frozen" => self::ruleFrozen($tags),
			"below_y40" => self::ruleBelow40(),
			"hard_only" => self::ruleHardOnly(),
			"dark_cave_stone" => self::ruleDarkCaveOnStone(),
			"empty" => self::ruleNoConditions(),
		];
		$index = new SpawnRuleIndex($rules, $tags);

		foreach([SpawnBand::SURFACE, SpawnBand::CAVE] as $band){
			foreach([0, 1, 3] as $difficulty){
				foreach([self::FROZEN_BIOME_ID, 401, 402] as $biomeId){
					foreach([BlockTypeIds::STONE, BlockTypeIds::WATER, BlockTypeIds::LAVA] as $feetBlock){
						foreach([10, 39, 40, 41, 80] as $y){
							$surfaceY = $band === SpawnBand::SURFACE ? $y - 1 : $y + 10;
							$candidates = $index->candidatesFor($biomeId, $band, $difficulty, $feetBlock);
							foreach(array_values($rules) as $rule){
								$identifier = $rule->getIdentifier();
								if(count($rule->getGroups()) === 0){
									continue; // no-conditions rules never spawn (index skips them too)
								}
								// The pipeline's liquid opt-in (docs/spawning.md): at a liquid
								// position only rule sets that explicitly declare that liquid are
								// attempted. The brute-force oracle must mirror that, or it would
								// expect a land rule set (e.g. habitat-band-only) to be a candidate
								// on water/lava, which the index correctly rejects.
								if(self::isLiquid($feetBlock) && !self::declaresLiquid($rule, $feetBlock)){
									continue;
								}
								if(self::bruteForceMatches(
									$rule,
									$biomeId,
									$band,
									$difficulty,
									$y,
									$surfaceY,
									$feetBlock
								)){
									self::assertTrue(
										self::containsIdentifier($candidates, $identifier),
										"index rejected rule set \"$identifier\" that brute-force matches at band=$band->name, diff=$difficulty, biome=$biomeId, feet=$feetBlock, y=$y"
									);
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * The index must omit a rule set it folded as impossible (e.g. a group whose
	 * difficulty conditions are mutually exclusive — min above max), because such a rule
	 * can provably never match.
	 */
	public function testImpossibleRuleIsNotOffered() : void{
		$rules = [
			// Two folded difficulty constraints that meet in a contradiction: the group
			// needs difficulty >=3 AND <=2 at the same position — impossible.
			"impossible" => new SpawnRules("minecraft:impossible", MobCategory::MONSTER, [
				self::group([new DifficultyFilter(3, 3), new DifficultyFilter(1, 2)]),
			]),
			"possible" => new SpawnRules("minecraft:possible", MobCategory::MONSTER, [
				self::group([new DifficultyFilter(1, 3)]),
			]),
		];
		$index = new SpawnRuleIndex($rules, self::tags());

		$candidates = $index->candidatesFor(401, SpawnBand::CAVE, 2, BlockTypeIds::STONE);
		self::assertFalse(self::containsIdentifier($candidates, "minecraft:impossible"));
		self::assertTrue(self::containsIdentifier($candidates, "minecraft:possible"));
	}

	private static function bruteForceMatches(
		SpawnRules $rule,
		int $biomeId,
		SpawnBand $band,
		int $difficulty,
		int $y,
		int $surfaceY,
		int $feetBlock
	) : bool{
		$env = new FixtureSpawnEnvironment($biomeId, $surfaceY, $feetBlock, self::LIGHT);
		foreach($rule->getGroups() as $group){
			$allPass = true;
			foreach($group->getConditions() as $condition){
				$ctx = new SpawnConditionContext(
					env: $env,
					x: 0,
					y: $y,
					z: 0,
					difficulty: $difficulty,
					weatherLightPenalty: 0,
					nearestPlayerDistance: 40.0
				);
				if(!$condition->test($ctx)){
					$allPass = false;
					break;
				}
			}
			if($allPass){
				return true;
			}
		}

		return false;
	}

	private static function isLiquid(int $feetBlock) : bool{
		return $feetBlock === BlockTypeIds::WATER || $feetBlock === BlockTypeIds::LAVA;
	}

	/**
	 * Whether any group of the rule explicitly declares that the given liquid must be the
	 * feet block (the condition the pipeline's liquid opt-in keys on).
	 */
	private static function declaresLiquid(SpawnRules $rule, int $liquidTypeId) : bool{
		foreach($rule->getGroups() as $group){
			foreach($group->getConditions() as $condition){
				if($condition instanceof SpawnsInLiquid && $condition->getRequiredLiquidTypeId() === $liquidTypeId){
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @phpstan-param list<SpawnRules> $candidates
	 */
	private static function containsIdentifier(array $candidates, string $identifier) : bool{
		foreach($candidates as $candidate){
			if($candidate->getIdentifier() === $identifier){
				return true;
			}
		}

		return false;
	}

	private static function tags() : TestBiomeTagResolver{
		return new TestBiomeTagResolver();
	}

	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 */
	private static function group(array $conditions) : SpawnConditionGroup{
		return new SpawnConditionGroup($conditions);
	}

	private static function ruleSurface() : SpawnRules{
		return new SpawnRules("minecraft:surface_animal", MobCategory::ANIMAL, [
			self::group([new HabitatBandCondition([SpawnBand::SURFACE])]),
		]);
	}

	private static function ruleCave() : SpawnRules{
		return new SpawnRules("minecraft:cave_monster", MobCategory::MONSTER, [
			self::group([new HabitatBandCondition([SpawnBand::CAVE])]),
		]);
	}

	private static function ruleUnderwater() : SpawnRules{
		return new SpawnRules("minecraft:wet_monster", MobCategory::MONSTER, [
			self::group([new SpawnsInLiquid(BlockTypeIds::WATER)]),
		]);
	}

	private static function ruleBright() : SpawnRules{
		return new SpawnRules("minecraft:diurnal", MobCategory::ANIMAL, [
			self::group([new BrightnessFilter(7, 15, false)]),
		]);
	}

	private static function ruleFrozen(BiomeTagResolver $tags) : SpawnRules{
		return new SpawnRules("minecraft:frozen", MobCategory::ANIMAL, [
			self::group([new BiomeTagCondition($tags, ["frozen"], [])]),
		]);
	}

	private static function ruleBelow40() : SpawnRules{
		return new SpawnRules("minecraft:below_y40", MobCategory::MONSTER, [
			self::group([new HeightFilter(null, 40)]),
		]);
	}

	private static function ruleHardOnly() : SpawnRules{
		return new SpawnRules("minecraft:hard_only", MobCategory::MONSTER, [
			self::group([new DifficultyFilter(3, 3)]),
		]);
	}

	private static function ruleDarkCaveOnStone() : SpawnRules{
		return new SpawnRules("minecraft:dark_cave_stone", MobCategory::MONSTER, [
			self::group([
				new HabitatBandCondition([SpawnBand::CAVE]),
				new BrightnessFilter(0, 7, false),
				new SpawnsOnBlock([BlockTypeIds::STONE => true], false),
			]),
		]);
	}

	private static function ruleNoConditions() : SpawnRules{
		return new SpawnRules("minecraft:empty", MobCategory::MONSTER, []);
	}
}

/** Fixture environment carrying the position facts the conditions read. */
final class FixtureSpawnEnvironment implements SpawnEnvironment{
	public function __construct(
		private readonly int $biomeId,
		private readonly int $surfaceY,
		private readonly int $feetBlock,
		private readonly int $light
	){}

	public function getBiomeId() : int{
		return $this->biomeId;
	}

	public function getSurfaceY() : int{
		return $this->surfaceY;
	}

	public function getLight() : int{
		return $this->light;
	}

	public function getBlockTypeId() : int{
		return $this->feetBlock;
	}

	public function getBelowBlockTypeId() : int{
		return BlockTypeIds::STONE; // the ground under the feet
	}

	public function countNearby(string $identifier) : int{
		return 0;
	}

	public function getTime() : int{
		return 0;
	}

	public function getTimeOfDay() : int{
		return 0;
	}
}

/** Deterministic resolver: one biome id carries "frozen", all others carry none. */
final class TestBiomeTagResolver implements BiomeTagResolver{
	public function getTags(int $biomeId) : array{
		return $biomeId === SpawnRuleIndexTest::FROZEN_BIOME_ID ? ["frozen"] : [];
	}
}