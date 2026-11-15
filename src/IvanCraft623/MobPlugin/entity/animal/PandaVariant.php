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

namespace IvanCraft623\MobPlugin\entity\animal;

use function spl_object_id;

/**
 * @phpstan-type TData array{
 *     health: int,
 *     speed: float,
 *     panicSpeed: float,
 *     damage: int,
 *     snackCooldown: int,
 *     snackMinCooldown: int,
 *     snackStopChance: float,
 *     lookMinCount: int,
 *     lookMaxCount: int,
 *     lookProbability: float,
 *     lookCooldown: int,
 *     rollProbability: float,
 *     sneezeProbability: float
 * }
 */
enum PandaVariant{
	case NORMAL;
	case LAZY;
	case WORRIED;
	case PLAYFUL;
	case BROWN;
	case WEAK;
	case AGGRESSIVE;

	/**
	 * Stats, and the numbers of the goals that every panda has, not their priorities. Speeds are in blocks per tick, cooldowns in ticks, chances per tick
	 * (the look chance per check); the roll and sneeze chances are of a cub, and a playful panda rolls at any age.
	 *
	 * @phpstan-return TData
	 */
	private function getData() : array{
		/** @phpstan-var array<int, TData> $cache */
		static $cache = [];

		return $cache[spl_object_id($this)] ??= match($this){
			self::LAZY => [
				"health" => 20, "speed" => 0.07, "panicSpeed" => 2.5, "damage" => 2,
				"snackCooldown" => 350, "snackMinCooldown" => 200, "snackStopChance" => 0.0011,
				"lookMinCount" => 3, "lookMaxCount" => 6, "lookProbability" => 0.02, "lookCooldown" => 500,
				"rollProbability" => 0.0016, "sneezeProbability" => 0.0001666
			],
			self::PLAYFUL => [
				"health" => 20, "speed" => 0.15, "panicSpeed" => 1.25, "damage" => 2,
				"snackCooldown" => 450, "snackMinCooldown" => 400, "snackStopChance" => 0.001334,
				"lookMinCount" => 2, "lookMaxCount" => 5, "lookProbability" => 0.01, "lookCooldown" => 600,
				"rollProbability" => 0.013, "sneezeProbability" => 0.0001666
			],
			self::WEAK => [
				"health" => 10, "speed" => 0.15, "panicSpeed" => 1.25, "damage" => 2,
				"snackCooldown" => 450, "snackMinCooldown" => 400, "snackStopChance" => 0.001334,
				"lookMinCount" => 2, "lookMaxCount" => 5, "lookProbability" => 0.01, "lookCooldown" => 600,
				"rollProbability" => 0.0016, "sneezeProbability" => 0.002
			],
			self::AGGRESSIVE => [
				"health" => 20, "speed" => 0.15, "panicSpeed" => 1.25, "damage" => 6,
				"snackCooldown" => 450, "snackMinCooldown" => 400, "snackStopChance" => 0.001334,
				"lookMinCount" => 2, "lookMaxCount" => 5, "lookProbability" => 0.01, "lookCooldown" => 600,
				"rollProbability" => 0.0016, "sneezeProbability" => 0.0001666
			],
			default => [
				"health" => 20, "speed" => 0.15, "panicSpeed" => 1.25, "damage" => 2,
				"snackCooldown" => 450, "snackMinCooldown" => 400, "snackStopChance" => 0.001334,
				"lookMinCount" => 2, "lookMaxCount" => 5, "lookProbability" => 0.01, "lookCooldown" => 600,
				"rollProbability" => 0.0016, "sneezeProbability" => 0.0001666
			]
		};
	}

	public function getMaxHealth() : int{ return $this->getData()["health"]; }

	/** Blocks per tick. */
	public function getMovementSpeed() : float{ return $this->getData()["speed"]; }

	public function getPanicSpeedModifier() : float{ return $this->getData()["panicSpeed"]; }

	public function getAttackDamage() : int{ return $this->getData()["damage"]; }

	/** Ticks. */
	public function getSnackCooldown() : int{ return $this->getData()["snackCooldown"]; }

	/** Ticks. */
	public function getSnackMinCooldown() : int{ return $this->getData()["snackMinCooldown"]; }

	/** Chance per tick of stopping a snack once it has been eaten for a while. */
	public function getSnackStopChance() : float{ return $this->getData()["snackStopChance"]; }

	public function getLookAndSitMinLookCount() : int{ return $this->getData()["lookMinCount"]; }

	public function getLookAndSitMaxLookCount() : int{ return $this->getData()["lookMaxCount"]; }

	public function getLookAndSitProbability() : float{ return $this->getData()["lookProbability"]; }

	/** Ticks. */
	public function getLookAndSitCooldown() : int{ return $this->getData()["lookCooldown"]; }

	/** Chance per tick of a cub rolling over; a playful panda rolls at any age. */
	public function getRollProbability() : float{ return $this->getData()["rollProbability"]; }

	/** Chance per tick of a cub sneezing. */
	public function getSneezeProbability() : float{ return $this->getData()["sneezeProbability"]; }
}
