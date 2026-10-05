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

namespace IvanCraft623\MobPlugin\despawning;

use pocketmine\entity\Entity;
use pocketmine\world\World;

/**
 * When the entities of a class despawn. The defaults are the rule of most vanilla mobs.
 *
 * @phpstan-template TEntity of Entity
 */
final class DespawnRule{
	/** The light vanilla monsters become inactive faster from. */
	public const MONSTER_LIGHT = 13;

	/**
	 * @param int      $minDistance              a player closer resets the inactivity time
	 * @param int      $maxDistance              with no player closer, it despawns at once
	 * @param int      $inactivityTime           ticks inactive before the roll
	 * @param int      $despawnChance            1 in this many per tick; 0 for always
	 * @param bool     $despawnsAwayFromPlayers  false leaves only the difficulty and the maximum lifetime
	 * @param int|null $fasterInactivityMinLight null for never
	 * @param int      $fasterInactivityBonus    extra ticks per tick in that light
	 * @param int|null $maxLifetime              in ticks; a name tag stops it, persistence doesn't
	 *
	 * @phpstan-param class-string<TEntity>           $entityClass
	 * @phpstan-param (\Closure(TEntity) : bool)|null $difficultyCondition which entities are removed outside the difficulties
	 * @phpstan-param (\Closure(TEntity) : bool)|null $persistentWhile
	 */
	public function __construct(
		public readonly string $entityClass,
		public readonly int $minDistance = 32,
		public readonly int $maxDistance = 128,
		public readonly int $inactivityTime = 600,
		public readonly int $despawnChance = 800,
		public readonly bool $despawnsAwayFromPlayers = true,
		public readonly int $minDifficulty = World::DIFFICULTY_PEACEFUL,
		public readonly int $maxDifficulty = World::DIFFICULTY_HARD,
		public readonly ?int $fasterInactivityMinLight = null,
		public readonly int $fasterInactivityBonus = 2,
		public readonly bool $persistentWithNameTag = true,
		public readonly ?int $maxLifetime = null,
		private readonly ?\Closure $difficultyCondition = null,
		private readonly ?\Closure $persistentWhile = null
	){}

	/**
	 * The rule of most vanilla monsters: removed in peaceful, inactive faster in the light.
	 *
	 * @phpstan-template T of Entity
	 *
	 * @phpstan-param class-string<T>           $entityClass
	 * @phpstan-param (\Closure(T) : bool)|null $difficultyCondition
	 * @phpstan-param (\Closure(T) : bool)|null $persistentWhile
	 *
	 * @phpstan-return self<T>
	 */
	public static function monster(
		string $entityClass,
		?int $maxLifetime = null,
		?\Closure $difficultyCondition = null,
		?\Closure $persistentWhile = null
	) : self{
		return new self(
			$entityClass,
			minDifficulty: World::DIFFICULTY_EASY,
			fasterInactivityMinLight: self::MONSTER_LIGHT,
			maxLifetime: $maxLifetime,
			difficultyCondition: $difficultyCondition,
			persistentWhile: $persistentWhile
		);
	}

	public function shouldDespawnInDifficulty(Entity $entity, int $difficulty) : bool{
		if($difficulty >= $this->minDifficulty && $difficulty <= $this->maxDifficulty){
			return false;
		}

		return $this->difficultyCondition === null || ($entity instanceof $this->entityClass && ($this->difficultyCondition)($entity));
	}

	public function keepsPersistent(Entity $entity) : bool{
		return $this->persistentWhile !== null && $entity instanceof $this->entityClass && ($this->persistentWhile)($entity);
	}
}