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

namespace IvanCraft623\MobPlugin\spawning\parse;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\HabitatBandCondition;
use IvanCraft623\MobPlugin\spawning\payload\Herd;
use IvanCraft623\MobPlugin\spawning\payload\PermuteType;
use IvanCraft623\MobPlugin\spawning\payload\SpawnConditionGroup;
use IvanCraft623\MobPlugin\spawning\payload\SpawnEvent;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use function array_values;
use function count;

/**
 * Accumulator a component parser fills while compiling one condition object; produces
 * the immutable SpawnConditionGroup. Components may parse in any order — payloads are
 * last-write, conditions accumulate. Parsers report unusable values by throwing
 * SpawnParseException — the strict loader has no warning channel.
 */
final class SpawnGroupBuilder{
	/** @phpstan-var list<SpawnCondition> */
	private array $conditions = [];

	private int $weight = 1;

	private ?Herd $herd = null;

	/** @phpstan-var list<PermuteType> */
	private array $permuteTypes = [];

	private ?SpawnEvent $event = null;

	/** @phpstan-var array<string, SpawnBand>|null habitat bands accepted so far (keyed by SpawnBand::name), null until a marker parses */
	private ?array $habitatBands = null;

	public function __construct(
		private readonly string $identifier
	){}

	public function getIdentifier() : string{
		return $this->identifier;
	}

	public function addCondition(SpawnCondition $condition) : void{
		$this->conditions[] = $condition;
	}

	/**
	 * Accepts one habitat band for this condition. The spawns_on_surface and
	 * spawns_underground registrations are independent parsers — each calls this for
	 * its own band, and build() unions them: both markers = both bands allowed (one
	 * HabitatBandCondition over the union, no cross-parser peeking), one marker = that band
	 * pinned. Exactly one accepted band also pins the group's herd band.
	 */
	public function allowHabitatBand(SpawnBand $band) : void{
		if($this->habitatBands === null){
			$this->habitatBands = [$band->name => $band];
		}else{
			$this->habitatBands[$band->name] = $band;
		}
	}

	public function setWeight(int $weight) : void{
		$this->weight = $weight;
	}

	public function setHerd(Herd $herd) : void{
		$this->herd = $herd;
	}

	/**
	 * @phpstan-param list<PermuteType> $permuteTypes
	 */
	public function setPermuteTypes(array $permuteTypes) : void{
		$this->permuteTypes = $permuteTypes;
	}

	public function setEvent(SpawnEvent $event) : void{
		$this->event = $event;
	}

	public function build() : SpawnConditionGroup{
		$habitatBand = null;
		if($this->habitatBands !== null && count($this->habitatBands) === 1){
			// A single marker pins the band; both markers = any band (no condition).
			$bands = array_values($this->habitatBands);
			$this->conditions[] = new HabitatBandCondition($bands);
			$habitatBand = $bands[0];
		}

		return new SpawnConditionGroup(
			$this->conditions,
			$this->weight,
			$this->herd,
			$this->permuteTypes,
			$this->event,
			$habitatBand
		);
	}
}
