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

use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\entity\Entity;
use pocketmine\world\World;
use function max;

/**
 * Entities near a mob, refreshed every few ticks. Readers must validate them.
 */
final class NearbyEntities {

	public const REFRESH_INTERVAL = 10; //ticks

	/** Collected past the requested range to cover movement between refreshes. */
	public const MARGIN = 6.0; //blocks

	/** Mob movement that forces a refresh. */
	public const MAX_DRIFT = 2.0; //blocks

	private const MAX_DRIFT_SQUARED = self::MAX_DRIFT ** 2;

	/** @var array<int, Entity> */
	private array $entities = [];

	private ?World $world = null;

	/** Mob bounding box corner at the last refresh. */
	private float $minX = 0.0;

	private float $minY = 0.0;

	private float $minZ = 0.0;

	private float $horizontal = 0.0;

	private float $vertical = 0.0;

	private int $refreshedAt = 0;

	public function __construct(private Mob $mob) {
	}

	/** @return array<int, Entity> */
	public function get(float $horizontal, float $vertical) : array{
		if ($this->world !== $this->mob->getWorld() ||
			$horizontal > $this->horizontal ||
			$vertical > $this->vertical ||
			$this->mob->ticksLived - $this->refreshedAt >= self::REFRESH_INTERVAL ||
			$this->hasDrifted()
		) {
			$this->refresh(max($horizontal, $this->horizontal), max($vertical, $this->vertical));
		}

		return $this->entities;
	}

	private function hasDrifted() : bool{
		$box = $this->mob->getBoundingBox();
		$dx = $box->minX - $this->minX;
		$dy = $box->minY - $this->minY;
		$dz = $box->minZ - $this->minZ;

		return $dx * $dx + $dy * $dy + $dz * $dz > self::MAX_DRIFT_SQUARED;
	}

	private function refresh(float $horizontal, float $vertical) : void{
		$mob = $this->mob;
		$box = $mob->getBoundingBox();
		$this->world = $mob->getWorld();
		$this->entities = $this->world->getNearbyEntities(
			$box->expandedCopy($horizontal + self::MARGIN, $vertical + self::MARGIN, $horizontal + self::MARGIN),
			$mob
		);
		$this->minX = $box->minX;
		$this->minY = $box->minY;
		$this->minZ = $box->minZ;
		$this->horizontal = $horizontal;
		$this->vertical = $vertical;
		$this->refreshedAt = $mob->ticksLived;
	}
}