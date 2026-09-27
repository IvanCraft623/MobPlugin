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

use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use pocketmine\block\BlockTypeIds;
use function array_values;
use function count;

/**
 * Set-level planning over compiled rule sets: folds each rule set's conditions into a
 * conservative SpawnConstraint and answers "which rule sets could possibly match this
 * position" in cheap set comparisons. There is deliberately no environment → category
 * switch — the conditions are the source of truth. See candidatesFor() for the one
 * invariant the conditions cannot express (liquid opt-in).
 */
final class SpawnRuleIndex{
	/** @phpstan-var list<array{SpawnRules, SpawnConstraint}> */
	private array $entries = [];

	/**
	 * @phpstan-param array<string, SpawnRules> $rules
	 */
	public function __construct(
		array $rules,
		private readonly BiomeTagResolver $tags
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
	 * a returned rule set may still fail its conditions; a rejected one provably never
	 * matches. At a liquid position only rule sets that explicitly declare that liquid
	 * are viable (the liquid opt-in invariant); at land, liquid-declaring rules are
	 * skipped.
	 *
	 * @phpstan-return list<SpawnRules>
	 */
	public function candidatesFor(int $biomeId, SpawnBand $band, int $difficulty, int $feetBlockTypeId) : array{
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

		return array_values($result);
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
