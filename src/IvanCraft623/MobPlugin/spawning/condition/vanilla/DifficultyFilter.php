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
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\plan\DifficultyConstrained;
use pocketmine\world\World;
use function implode;

/**
 * difficulty_filter: names resolve through World::getDifficultyFromString() (-1 =
 * unknown name = parse problem). Unspecified bounds default to the schema-declared
 * difficulty names (SpawnSchema::DIFFICULTY_MIN / DIFFICULTY_MAX).
 */
final class DifficultyFilter implements SpawnCondition, DifficultyConstrained{
	/**
	 * @phpstan-throws \InvalidArgumentException on an unknown difficulty name or an
	 *     inverted range
	 */
	public function __construct(
		private readonly int $min,
		private readonly int $max
	){
		if($this->min > $this->max){
			throw new \InvalidArgumentException("DifficultyFilter minimum ($min) must not exceed maximum ($max)");
		}
	}

	/**
	 * @phpstan-throws \InvalidArgumentException
	 */
	public static function fromNames(?string $minName, ?string $maxName) : self{
		return new self(self::fromName($minName ?? SpawnSchema::DIFFICULTY_MIN), self::fromName($maxName ?? SpawnSchema::DIFFICULTY_MAX));
	}

	private static function fromName(string $name) : int{
		$difficulty = World::getDifficultyFromString($name);
		if($difficulty === -1){
			throw new \InvalidArgumentException("unknown difficulty \"$name\"; names declared by the schema: " . implode(", ", SpawnSchema::DIFFICULTY_CASES));
		}

		return $difficulty;
	}

	public function getMinDifficulty() : int{
		return $this->min;
	}

	public function getMaxDifficulty() : int{
		return $this->max;
	}

	public function getEvaluationCost() : int{
		return 1; // pure context-field compare
	}

	public function test(SpawnConditionContext $ctx) : bool{
		return $ctx->difficulty >= $this->min && $ctx->difficulty <= $this->max;
	}
}
