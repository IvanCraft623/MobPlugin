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

use IvanCraft623\MobPlugin\spawning\condition\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnsOnBlock;
use IvanCraft623\MobPlugin\spawning\condition\StubContext;
use IvanCraft623\MobPlugin\spawning\plan\SpawnRuleIndex;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use PHPUnit\Framework\TestCase;
use function array_values;
use function count;

/**
 * The planner index is an over-approximation: it must offer every rule set whose real
 * conditions could match a position (a superset of brute-force evaluation).
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
					foreach(SpawnLiquid::cases() as $feetLiquid){
						foreach([10, 39, 40, 41, 80] as $y){
							$surfaceY = $band === SpawnBand::SURFACE ? $y - 1 : $y + 10;
							$candidates = $index->candidatesFor($biomeId, $band, $difficulty, $feetLiquid);
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
								if($feetLiquid !== SpawnLiquid::NONE && !self::declaresLiquid($rule, $feetLiquid)){
									continue;
								}
								if(self::bruteForceMatches(
									$rule,
									$biomeId,
									$band,
									$difficulty,
									$y,
									$surfaceY,
									$feetLiquid
								)){
									self::assertTrue(
										self::containsIdentifier($candidates, $identifier),
										"index rejected rule set \"$identifier\" that brute-force matches at band=$band->name, diff=$difficulty, biome=$biomeId, feet=$feetLiquid->name, y=$y"
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
			"impossible" => self::rules("minecraft:impossible", "monster", [
				self::group([RangeCondition::difficulty(3, 3), RangeCondition::difficulty(1, 2)]),
			]),
			"possible" => self::rules("minecraft:possible", "monster", [
				self::group([RangeCondition::difficulty(1, 3)]),
			]),
		];
		$index = new SpawnRuleIndex($rules, self::tags());

		$candidates = $index->candidatesFor(401, SpawnBand::CAVE, 2, SpawnLiquid::NONE);
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
		SpawnLiquid $feetLiquid
	) : bool{
		$ctx = new StubContext(
			biomeId: $biomeId,
			band: $band,
			difficulty: $difficulty,
			feetLiquid: $feetLiquid,
			y: $y,
			groundY: $surfaceY,
			light: self::LIGHT,
			belowTypeId: BlockTypeIds::STONE,
			nearestPlayerDistance: 40.0
		);

		return $rule->check($ctx) !== null;
	}

	/**
	 * Whether any group of the rule explicitly declares that the given liquid must be the
	 * feet block (the condition the pipeline's liquid opt-in keys on).
	 */
	private static function declaresLiquid(SpawnRules $rule, SpawnLiquid $liquid) : bool{
		foreach($rule->getGroups() as $group){
			if($group->getRequiredLiquid() === $liquid){
				return true;
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

	private static function tags() : BiomeTagMap{
		return new BiomeTagMap([self::FROZEN_BIOME_ID => ["frozen"]]);
	}

	/**
	 * @phpstan-param list<SpawnCondition> $conditions
	 */
	private static function group(array $conditions) : SpawnRuleGroup{
		return new SpawnRuleGroup($conditions);
	}

	/**
	 * @phpstan-param list<SpawnRuleGroup> $groups
	 */
	private static function rules(string $identifier, string $categoryId, array $groups) : SpawnRules{
		return new SpawnRules($identifier, $categoryId, $groups, static fn() : Entity => throw new \LogicException("the index never spawns"));
	}

	private static function ruleSurface() : SpawnRules{
		return self::rules("minecraft:surface_animal", "animal", [
			self::group([RangeCondition::band(SpawnBand::SURFACE)]),
		]);
	}

	private static function ruleCave() : SpawnRules{
		return self::rules("minecraft:cave_monster", "monster", [
			self::group([RangeCondition::band(SpawnBand::CAVE)]),
		]);
	}

	private static function ruleUnderwater() : SpawnRules{
		return self::rules("minecraft:wet_monster", "monster", [
			self::group([RangeCondition::liquid(SpawnLiquid::WATER)]),
		]);
	}

	private static function ruleBright() : SpawnRules{
		return self::rules("minecraft:diurnal", "animal", [
			self::group([RangeCondition::brightness(7, 15)]),
		]);
	}

	private static function ruleFrozen(BiomeTagMap $tags) : SpawnRules{
		return self::rules("minecraft:frozen", "animal", [
			self::group([new BiomeTagCondition($tags, ["frozen"], [])]),
		]);
	}

	private static function ruleBelow40() : SpawnRules{
		return self::rules("minecraft:below_y40", "monster", [
			self::group([RangeCondition::height(null, 40)]),
		]);
	}

	private static function ruleHardOnly() : SpawnRules{
		return self::rules("minecraft:hard_only", "monster", [
			self::group([RangeCondition::difficulty(3, 3)]),
		]);
	}

	private static function ruleDarkCaveOnStone() : SpawnRules{
		return self::rules("minecraft:dark_cave_stone", "monster", [
			self::group([
				RangeCondition::band(SpawnBand::CAVE),
				RangeCondition::brightness(0, 7),
				new SpawnsOnBlock([BlockTypeIds::STONE => true], false),
			]),
		]);
	}

	private static function ruleNoConditions() : SpawnRules{
		return self::rules("minecraft:empty", "monster", []);
	}
}
