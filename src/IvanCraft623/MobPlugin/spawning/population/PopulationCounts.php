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

namespace IvanCraft623\MobPlugin\spawning\population;

use IvanCraft623\MobPlugin\spawning\SpawnBand;

/**
 * How many mobs of each category and of each type there are per band, in a chunk or in
 * a region.
 */
final class PopulationCounts{
	/** @phpstan-var array<string, int> band value . category id => count */
	private array $categories = [];

	/** @phpstan-var array<string, int> band value . entity identifier => count */
	private array $identifiers = [];

	/**
	 * @phpstan-param array<int, array<string, int>> $categoryCounts   band value => category id => count
	 * @phpstan-param array<int, array<string, int>> $identifierCounts band value => entity identifier => count
	 */
	public static function of(array $categoryCounts = [], array $identifierCounts = []) : self{
		$result = new self();
		foreach($categoryCounts as $band => $counts){
			foreach($counts as $id => $count){
				$result->categories[$band . $id] = $count;
			}
		}
		foreach($identifierCounts as $band => $counts){
			foreach($counts as $id => $count){
				$result->identifiers[$band . $id] = $count;
			}
		}

		return $result;
	}

	public function getCategoryCount(string $categoryId, SpawnBand $band) : int{
		return $this->categories[$band->value . $categoryId] ?? 0;
	}

	public function getIdentifierCount(string $identifier, SpawnBand $band) : int{
		return $this->identifiers[$band->value . $identifier] ?? 0;
	}

	/**
	 * @internal
	 */
	public function add(SpawnBand $band, string $categoryId, string $identifier) : void{
		$key = $band->value . $categoryId;
		$this->categories[$key] = ($this->categories[$key] ?? 0) + 1;
		$key = $band->value . $identifier;
		$this->identifiers[$key] = ($this->identifiers[$key] ?? 0) + 1;
	}

	/**
	 * @internal
	 */
	public function merge(self $other) : void{
		foreach($other->categories as $key => $count){
			$this->categories[$key] = ($this->categories[$key] ?? 0) + $count;
		}
		foreach($other->identifiers as $key => $count){
			$this->identifiers[$key] = ($this->identifiers[$key] ?? 0) + $count;
		}
	}
}
