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

/**
 * One biome tag condition compiled from a biome_filter leaf (is_snow_covered ≈
 * required "frozen"). Tags for the position's biome come from the injected
 * BiomeTagResolver; BiomeFilterParser composes trees of these leaves at parse time.
 */
final class BiomeTagCondition implements SpawnCondition, BiomeConstrained{
	/**
	 * @phpstan-param list<string> $required
	 * @phpstan-param list<string> $forbidden
	 */
	public function __construct(
		private readonly BiomeTagResolver $tags,
		private readonly array $required,
		private readonly array $forbidden
	){}

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

	public function test(SpawnConditionContext $ctx) : bool{
		$tagMap = [];
		foreach($this->tags->getTags($ctx->env->getBiomeId()) as $tag){
			$tagMap[$tag] = true;
		}
		foreach($this->required as $tag){
			if(!isset($tagMap[$tag])){
				return false;
			}
		}
		foreach($this->forbidden as $tag){
			if(isset($tagMap[$tag])){
				return false;
			}
		}

		return true;
	}
}
