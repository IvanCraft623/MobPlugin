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

namespace IvanCraft623\MobPlugin\spawning\plan;

use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use pocketmine\block\BlockTypeIds;
use function array_values;
use function count;

/**
 * Set-level planning over compiled rule sets: folds conditions into a conservative
 * SpawnConstraint and cheaply answers which rule sets could match a position.
 */
final class SpawnRuleIndex{
	/**
	 * Upper bound on candidateCache entries. The key space (biomeId × band × difficulty ×
	 * feet block) is naturally small and bounded per world, but a hard cap keeps the memo
	 * from growing without bound across many data-driven feet block types; when exceeded
	 * the whole memo is rebuilt lazily.
	 */
	private const CANDIDATE_CACHE_CAP = 4096;

	/** @phpstan-var list<array{SpawnRules, SpawnConstraint}> */
	private array $entries = [];

	/**
	 * Memo of candidatesFor results keyed by the position profile. The constraint scan is
	 * the same for every candidate sharing a (biome, band, difficulty, feet block), and a
	 * tick evaluates many candidates, so this turns the per-candidate O(entries) walk into
	 * an associative-array lookup.
	 *
	 * @phpstan-var array<string, list<SpawnRules>>
	 */
	private array $candidateCache = [];

	/**
	 * @phpstan-param array<string, SpawnRules> $rules
	 */
	public function __construct(
		array $rules,
		private readonly BiomeTagMap $tags
	){
		foreach($rules as $rule){
			if(count($rule->getGroups()) === 0){
				continue; // rule sets without conditions (e.g. wither_skeleton) never spawn
			}
			$constraint = self::foldRule($rule);
			if($constraint->isImpossible()){
				continue;
			}
			$this->entries[] = [$rule, $constraint];
		}
	}

	/**
	 * Rule sets that could possibly match the given position profile. Conservative:
	 * a rejected one provably never matches; a returned one may still fail its
	 * conditions. Liquid positions only consider rules that declare that liquid.
	 *
	 * @phpstan-return list<SpawnRules>
	 */
	public function candidatesFor(int $biomeId, SpawnBand $band, int $difficulty, int $feetBlockTypeId) : array{
		$key = $biomeId . "|" . $band->name . "|" . $difficulty . "|" . $feetBlockTypeId;
		$cached = $this->candidateCache[$key] ?? null;
		if($cached !== null){
			return $cached;
		}

		$isLiquid = $feetBlockTypeId === BlockTypeIds::WATER || $feetBlockTypeId === BlockTypeIds::LAVA;
		$biomeTags = null;
		$result = [];
		foreach($this->entries as [$rule, $constraint]){
			if(!$constraint->acceptsBand($band)){
				continue;
			}
			if($constraint->requiredLiquid !== null){
				if($constraint->requiredLiquid !== $feetBlockTypeId){
					continue;
				}
			}elseif($isLiquid){
				continue; // liquid position without a matching liquid declaration
			}
			if(!$constraint->acceptsDifficulty($difficulty)){
				continue;
			}
			if($constraint->requiredTags !== null || $constraint->forbiddenTags !== null){
				$biomeTags ??= $this->tags->getTags($biomeId);
				if(!$constraint->acceptsBiomeTags($biomeTags)){
					continue;
				}
			}
			$result[$rule->getIdentifier()] ??= $rule;
		}

		$viable = array_values($result);
		if(count($this->candidateCache) >= self::CANDIDATE_CACHE_CAP){
			$this->candidateCache = []; // bounded memo — rebuild lazily if it outgrows its budget
		}
		$this->candidateCache[$key] = $viable;

		return $viable;
	}

	/**
	 * Folds a whole rule set: OR over its condition groups (ordered alternatives — the
	 * rule matches wherever any group matches).
	 */
	private static function foldRule(SpawnRules $rule) : SpawnConstraint{
		$constraint = null;
		foreach($rule->getGroups() as $group){
			$groupConstraint = self::foldGroup($group->getConditions());
			$constraint = $constraint === null ? $groupConstraint : $constraint->orFold($groupConstraint);
		}

		return $constraint ?? SpawnConstraint::unconstrained();
	}

	/**
	 * Folds one condition group: its conditions are ANDed, so every condition that
	 * carries planning metadata narrows the group's constraint. AllOf trees are walked
	 * (provable AND paths); AnyOf/Not contribute nothing (conservative fallback).
	 *
	 * @phpstan-param list<SpawnCondition> $conditions
	 */
	private static function foldGroup(array $conditions) : SpawnConstraint{
		$constraint = SpawnConstraint::unconstrained();
		foreach($conditions as $condition){
			$constraint = $constraint->andFold(self::foldCondition($condition));
			if($constraint->isImpossible()){
				return $constraint;
			}
		}

		return $constraint;
	}

	private static function foldCondition(SpawnCondition $condition) : SpawnConstraint{
		$constraint = SpawnConstraint::unconstrained();
		if($condition instanceof AllOf){
			foreach($condition->getChildren() as $child){
				$constraint = $constraint->andFold(self::foldCondition($child));
				if($constraint->isImpossible()){
					return $constraint;
				}
			}

			return $constraint;
		}
		if($condition instanceof HabitatConstrained){
			$constraint = $constraint->andFold(SpawnConstraint::bands($condition->getAllowedHabitatBands()));
		}
		if($condition instanceof LiquidConstrained){
			$constraint = $constraint->andFold(SpawnConstraint::liquid($condition->getRequiredLiquidTypeId()));
		}
		if($condition instanceof DifficultyConstrained){
			$constraint = $constraint->andFold(SpawnConstraint::difficulty($condition->getMinDifficulty(), $condition->getMaxDifficulty()));
		}
		if($condition instanceof BiomeConstrained){
			$constraint = $constraint->andFold(SpawnConstraint::biomeTags($condition->getRequiredBiomeTags(), $condition->getForbiddenBiomeTags()));
		}

		return $constraint;
	}
}
