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

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\spawning\BandCounts;
use function spl_object_id;

/**
 * Bedrock mob category: the case backing value IS the vanilla spawn-rule
 * "population_control" string. Category-level data (population caps per band, despawn
 * distance) lives on the enum as metadata.
 *
 * CREATURE is the internal sentinel default (Mob::getMobCategory()): it is NOT a Bedrock
 * population_control value and never appears in parsed spawn data. It gives mobs without
 * a classified family (`Animal`/`Monster`/`Ambient`) a zero-cap bucket so they are never
 * mistakenly counted against (or limited by) a real category's population cap.
 *
 * @phpstan-return array{int, mixed}
 */
enum MobCategory : string{
	case MONSTER = "monster";
	case ANIMAL = "animal";
	case AMBIENT = "ambient";
	case WATER_ANIMAL = "water_animal";
	case CAT = "cat";
	case CREATURE = "creature"; // internal sentinel, not a Bedrock population_control

	/**
	 * @phpstan-return array{0 : BandCounts, 1 : int} population control caps
	 *     (surface/cave) and despawn distance
	 */
	private function getMetadata() : array{
		/** @phpstan-var array<int, array{0 : BandCounts, 1 : int}> $cache */
		static $cache = [];

		return $cache[spl_object_id($this)] ??= match($this){
			// Caps: population control per 9x9 chunk region (minecraft.wiki/w/Mob_spawning —
			// Bedrock Edition), surface/cave; 0 = no natural spawns in that band.
			// Cats are not cluster-spawned on Bedrock (they come from village mechanics),
			// hence the 0 caps. CREATURE is the internal sentinel default (zero caps) so
			// unclassified mobs never consume a real category's population slot.
			self::MONSTER => [new BandCounts(8, 16), 64],
			self::ANIMAL => [new BandCounts(4, 0), 64],
			self::AMBIENT => [new BandCounts(0, 2), 32],
			self::WATER_ANIMAL => [new BandCounts(36, 0), 64],
			self::CAT => [new BandCounts(0, 0), 64],
			self::CREATURE => [new BandCounts(0, 0), 64]
		};
	}

	/**
	 * Population control caps for this category: limits per 9x9 chunk region, split into
	 * surface and cave bands.
	 */
	public function getPopulationCaps() : BandCounts{
		return $this->getMetadata()[0];
	}

	/**
	 * Mobs farther than this from every player despawn (natural despawn logic).
	 */
	public function getDespawnDistance() : int{
		return $this->getMetadata()[1];
	}

	public function getNoDespawnDistance() : int{
		return 32;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public static function fromValue(string $value) : self{
		$enum = self::tryFrom($value);
		if($enum === null){
			throw new \InvalidArgumentException("Invalid raw case '$value'");
		}

		return $enum;
	}
}
