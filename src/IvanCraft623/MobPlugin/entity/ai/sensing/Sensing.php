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

namespace IvanCraft623\MobPlugin\entity\ai\sensing;

use Closure;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\object\ItemEntity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use function count;
use const INF;

/**
 * What a mob perceives, computed on demand.
 */
class Sensing {

	/** Searches at least this wide share a {@see NearbyEntities} snapshot, narrower ones query the world. */
	public const SNAPSHOT_MIN_RANGE = 16.0; //blocks

	private NearbyEntities $nearby;

	/** @var array<int, bool> */
	private array $lineOfSight = [];

	public function __construct(private Mob $mob) {
		$this->nearby = new NearbyEntities($mob);
	}

	public function tick() : void{
		$this->lineOfSight = [];
	}

	public function canSee(Entity $entity) : bool{
		$id = $entity->getId();
		$cached = $this->lineOfSight[$id] ?? null;
		if ($cached !== null) {
			return $cached;
		}

		return $this->lineOfSight[$id] = $this->mob->canSee($entity);
	}

	/**
	 * Entities within the given extents of the mob. With $targetFirst, stops at the first match.
	 *
	 * @return list<Living>
	 */
	public function getNearbyLiving(float $horizontal, ?float $vertical = null, bool $targetFirst = false) : array{
		$vertical ??= $horizontal;
		$box = $this->box($horizontal, $vertical);

		return $this->collect($this->candidates($horizontal, $vertical, $box), Living::class, $box, $targetFirst);
	}

	/** @return list<Player> */
	public function getNearbyPlayers(float $horizontal, ?float $vertical = null, bool $targetFirst = false) : array{
		$vertical ??= $horizontal;
		$box = $this->box($horizontal, $vertical);

		return $this->collect($this->candidates($horizontal, $vertical, $box), Player::class, $box, $targetFirst);
	}

	/** @return list<ItemEntity> */
	public function getNearbyItems(float $horizontal, ?float $vertical = null, bool $targetFirst = false) : array{
		$vertical ??= $horizontal;
		$box = $this->box($horizontal, $vertical);

		return $this->collect($this->candidates($horizontal, $vertical, $box), ItemEntity::class, $box, $targetFirst);
	}

	/**
	 * Closest entity of a type. Conditions and filter only run on candidates closer than the best
	 * so far. Wide player searches read the world's player list.
	 *
	 * @template T of Living
	 * @phpstan-param class-string<T> $type
	 * @phpstan-param null|Closure(T) : bool $filter
	 * @phpstan-return T|null
	 */
	public function findNearest(
		string $type,
		float $horizontal,
		?float $vertical = null,
		?TargetingConditions $conditions = null,
		?Closure $filter = null,
		bool $fromEyes = false
	) : ?Living{
		$vertical ??= $horizontal;

		if ($type === Player::class && ($horizontal >= self::SNAPSHOT_MIN_RANGE || $vertical >= self::SNAPSHOT_MIN_RANGE)) {
			$pool = $this->mob->getWorld()->getPlayers();
			if (count($pool) === 0) {
				return null;
			}
			$box = $this->box($horizontal, $vertical);
		} else {
			$box = $this->box($horizontal, $vertical);
			$pool = $this->candidates($horizontal, $vertical, $box);
			if (count($pool) === 0) {
				return null;
			}
		}

		$origin = $fromEyes ? $this->mob->getEyePos() : $this->mob->getPosition();

		$nearest = null;
		$nearestDistanceSquared = INF;
		foreach ($pool as $candidate) {
			if (!$candidate instanceof $type || !$this->isPresent($candidate, $box)) {
				continue;
			}

			$distanceSquared = $candidate->getPosition()->distanceSquared($origin);
			if ($distanceSquared >= $nearestDistanceSquared ||
				($conditions !== null && !$conditions->test($this->mob, $candidate)) ||
				($filter !== null && !$filter($candidate))
			) {
				continue;
			}

			$nearest = $candidate;
			$nearestDistanceSquared = $distanceSquared;
		}

		return $nearest;
	}

	/** @return array<int, Entity> */
	private function candidates(float $horizontal, float $vertical, AxisAlignedBB $box) : array{
		if ($horizontal < self::SNAPSHOT_MIN_RANGE && $vertical < self::SNAPSHOT_MIN_RANGE) {
			return $this->mob->getWorld()->getNearbyEntities($box, $this->mob);
		}

		return $this->nearby->get($horizontal, $vertical);
	}

	private function box(float $horizontal, float $vertical) : AxisAlignedBB{
		return $this->mob->getBoundingBox()->expandedCopy($horizontal, $vertical, $horizontal);
	}

	/**
	 * @template T of Entity
	 * @phpstan-param array<int, Entity> $pool
	 * @phpstan-param class-string<T> $type
	 * @phpstan-return list<T>
	 */
	private function collect(array $pool, string $type, AxisAlignedBB $box, bool $targetFirst) : array{
		$result = [];
		foreach ($pool as $candidate) {
			if ($candidate instanceof $type && $this->isPresent($candidate, $box)) {
				$result[] = $candidate;
				if ($targetFirst) {
					break;
				}
			}
		}

		return $result;
	}

	private function isPresent(Entity $entity, AxisAlignedBB $box) : bool{
		return $entity->getBoundingBox()->intersectsWith($box) && !$entity->isClosed() && $entity->isAlive();
	}
}
