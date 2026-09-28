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

namespace IvanCraft623\MobPlugin\spawning\condition\vanilla;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\plan\BiomeConstrained;
use function array_keys;

/**
 * One biome tag condition compiled from a biome_filter leaf (is_snow_covered ≈
 * required "frozen"). Tags for the position's biome come from the injected
 * BiomeTagResolver; BiomeFilterParser composes trees of these leaves at parse time.
 */
final class BiomeTagCondition implements SpawnCondition, BiomeConstrained{
	/** @phpstan-var array<string, true> */
	private readonly array $requiredSet;

	/** @phpstan-var array<string, true> */
	private readonly array $forbiddenSet;

	/**
	 * @phpstan-param list<string> $required
	 * @phpstan-param list<string> $forbidden
	 */
	public function __construct(
		private readonly BiomeTagResolver $tags,
		private readonly array $required,
		private readonly array $forbidden
	){
		$this->requiredSet = self::toSet($required);
		$this->forbiddenSet = self::toSet($forbidden);
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getRequiredBiomeTags() : array{
		return $this->required;
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getForbiddenBiomeTags() : array{
		return $this->forbidden;
	}

	public function getEvaluationCost() : int{
		return 3; // enumerates the biome's tags through the resolver
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$tagMap = [];
		foreach($this->tags->getTags($ctx->env->getBiomeId()) as $tag){
			$tagMap[$tag] = true;
		}
		foreach(array_keys($this->requiredSet) as $tag){
			if(!isset($tagMap[$tag])){
				return false;
			}
		}
		foreach(array_keys($this->forbiddenSet) as $tag){
			if(isset($tagMap[$tag])){
				return false;
			}
		}

		return true;
	}

	/**
	 * @phpstan-param list<string> $tags
	 *
	 * @phpstan-return array<string, true>
	 */
	private static function toSet(array $tags) : array{
		$set = [];
		foreach($tags as $tag){
			$set[$tag] = true;
		}

		return $set;
	}
}
