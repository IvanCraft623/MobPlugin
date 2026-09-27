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

use IvanCraft623\MobPlugin\spawning\SpawnBand;
use function array_intersect;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function max;
use function min;

/**
 * Conservative summary of what a rule set can accept: habitat bands, difficulty range,
 * biome tags, required liquid. Planning metadata only — never decides a spawn. Every
 * rejection is provable (AND paths only; AnyOf/Not contribute nothing), every acceptance
 * still goes through the real conditions.
 */
final class SpawnConstraint{
	/**
	 * @phpstan-param array<string, true>|null $bands allowed habitat bands keyed by SpawnBand::name, null = any band
	 */
	public function __construct(
		public readonly ?array $bands,
		public readonly ?int $minDifficulty,
		public readonly ?int $maxDifficulty,
		/** @phpstan-var list<string>|null required biome tags, null = no requirement */
		public readonly ?array $requiredTags,
		/** @phpstan-var list<string>|null forbidden biome tags, null = none */
		public readonly ?array $forbiddenTags,
		/** @phpstan-var int|null BlockTypeIds::WATER/LAVA when the feet block is pinned */
		public readonly ?int $requiredLiquid,
		public readonly bool $impossible = false
	){}

	public static function unconstrained() : self{
		return new self(null, null, null, null, null, null, false);
	}

	/**
	 * @phpstan-param list<SpawnBand> $bands
	 */
	public static function bands(array $bands) : self{
		$map = [];
		foreach($bands as $band){
			$map[$band->name] = true;
		}

		return new self($map, null, null, null, null, null, false);
	}

	public static function difficulty(int $min, int $max) : self{
		return new self(null, $min, $max, null, null, null, false);
	}

	/**
	 * @phpstan-param list<string>|null $required
	 * @phpstan-param list<string>|null $forbidden
	 */
	public static function biomeTags(?array $required, ?array $forbidden) : self{
		return new self(null, null, null, $required, $forbidden, null, false);
	}

	public static function liquid(int $liquidTypeId) : self{
		return new self(null, null, null, null, null, $liquidTypeId, false);
	}

	private static function impossible() : self{
		return new self(null, null, null, null, null, null, true);
	}

	public function isImpossible() : bool{
		return $this->impossible;
	}

	public function andFold(self $other) : self{
		if($this->impossible || $other->impossible){
			return self::impossible();
		}
		$bands = $this->bands === null
			? $other->bands
			: ($other->bands === null ? $this->bands : self::intersectBands($this->bands, $other->bands));
		if($bands !== null && count($bands) === 0){ // contradictory band requirements
			return self::impossible();
		}
		$minDifficulty = $this->minDifficulty === null
			? $other->minDifficulty
			: ($other->minDifficulty === null ? $this->minDifficulty : max($this->minDifficulty, $other->minDifficulty));
		$maxDifficulty = $this->maxDifficulty === null
			? $other->maxDifficulty
			: ($other->maxDifficulty === null ? $this->maxDifficulty : min($this->maxDifficulty, $other->maxDifficulty));
		if($minDifficulty !== null && $maxDifficulty !== null && $minDifficulty > $maxDifficulty){
			return self::impossible();
		}
		$requiredTags = $this->requiredTags === null
			? $other->requiredTags
			: ($other->requiredTags === null ? $this->requiredTags : array_values(array_unique([...$this->requiredTags, ...$other->requiredTags])));
		$forbiddenTags = $this->forbiddenTags === null
			? $other->forbiddenTags
			: ($other->forbiddenTags === null ? $this->forbiddenTags : array_values(array_unique([...$this->forbiddenTags, ...$other->forbiddenTags])));
		$requiredLiquid = $this->requiredLiquid === null
			? $other->requiredLiquid
			: ($other->requiredLiquid === null ? $this->requiredLiquid : ($this->requiredLiquid === $other->requiredLiquid ? $this->requiredLiquid : null));
		if($requiredLiquid === null && $this->requiredLiquid !== null && $other->requiredLiquid !== null){
			return self::impossible(); // contradictory liquid requirements
		}

		return new self($bands, $minDifficulty, $maxDifficulty, $requiredTags, $forbiddenTags, $requiredLiquid, false);
	}

	/** Worst case both sides drop to unconstrained, which is always conservative. */
	public function orFold(self $other) : self{
		if($this->isImpossible()){
			return $other;
		}
		if($other->isImpossible()){
			return $this;
		}
		$bands = $this->bands === null || $other->bands === null
			? null
			: array_merge($this->bands, $other->bands);
		$minDifficulty = $this->minDifficulty !== null && $other->minDifficulty !== null
			? min($this->minDifficulty, $other->minDifficulty)
			: null;
		$maxDifficulty = $this->maxDifficulty !== null && $other->maxDifficulty !== null
			? max($this->maxDifficulty, $other->maxDifficulty)
			: null;
		$requiredTags = $this->requiredTags === null || $other->requiredTags === null
			? null
			: array_values(array_intersect($this->requiredTags, $other->requiredTags));
		$forbiddenTags = $this->forbiddenTags === null || $other->forbiddenTags === null
			? null
			: array_values(array_intersect($this->forbiddenTags, $other->forbiddenTags));
		$requiredLiquid = $this->requiredLiquid === null || $other->requiredLiquid === null
			? null
			: ($this->requiredLiquid === $other->requiredLiquid ? $this->requiredLiquid : null);

		return new self($bands, $minDifficulty, $maxDifficulty, $requiredTags, $forbiddenTags, $requiredLiquid);
	}

	public function acceptsBand(SpawnBand $band) : bool{
		return $this->bands === null || isset($this->bands[$band->name]);
	}

	public function acceptsDifficulty(int $difficulty) : bool{
		return ($this->minDifficulty === null || $difficulty >= $this->minDifficulty)
			&& ($this->maxDifficulty === null || $difficulty <= $this->maxDifficulty);
	}

	/**
	 * @phpstan-param list<string> $tags
	 */
	public function acceptsBiomeTags(array $tags) : bool{
		$tagMap = [];
		foreach($tags as $tag){
			$tagMap[$tag] = true;
		}
		if($this->requiredTags !== null){
			foreach($this->requiredTags as $tag){
				if(!isset($tagMap[$tag])){
					return false;
				}
			}
		}
		if($this->forbiddenTags !== null){
			foreach($this->forbiddenTags as $tag){
				if(isset($tagMap[$tag])){
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @phpstan-param array<string, true> $a
	 * @phpstan-param array<string, true> $b
	 * @phpstan-return array<string, true>
	 */
	private static function intersectBands(array $a, array $b) : array{
		$result = [];
		foreach($a as $name => $_){
			if(isset($b[$name])){
				$result[$name] = true;
			}
		}

		return $result;
	}
}
