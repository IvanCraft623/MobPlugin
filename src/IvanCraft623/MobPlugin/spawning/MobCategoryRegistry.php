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

namespace IvanCraft623\MobPlugin\spawning;

use pocketmine\utils\SingletonTrait;

use function array_keys;

final class MobCategoryRegistry{
	use SingletonTrait;

	public const CREATURE = "creature";
	public const MONSTER = "monster";
	public const ANIMAL = "animal";
	public const AMBIENT = "ambient";
	public const WATER_ANIMAL = "water_animal";
	public const CAT = "cat";

	/** @var array<string, MobCategory> keyed by category id */
	private array $categories = [];

	private function __construct(){
		$this->register(new MobCategory(self::CREATURE, new BandCounts(0, 0), 64));
		$this->register(new MobCategory(self::MONSTER, new BandCounts(8, 16), 64));
		$this->register(new MobCategory(self::ANIMAL, new BandCounts(4, 0), 64));
		$this->register(new MobCategory(self::AMBIENT, new BandCounts(0, 2), 32));
		$this->register(new MobCategory(self::WATER_ANIMAL, new BandCounts(36, 0), 64));
		$this->register(new MobCategory(self::CAT, new BandCounts(0, 0), 64)); //Cats are not cluster-spawned on Bedrock
	}

	/**
	 * Registers a category, replacing any existing category with the same id.
	 */
	public function register(MobCategory $category) : void{
		$this->categories[$category->id] = $category;
	}

	public function get(string $id) : ?MobCategory{
		return $this->categories[$id] ?? null;
	}

	/**
	 * Returns a category that must exist (e.g. the registered sentinel), throwing
	 * otherwise.
	 */
	public function getRequired(string $id) : MobCategory{
		$category = $this->categories[$id] ?? null;
		if($category === null){
			throw new \InvalidArgumentException("Mob category \"$id\" is not registered.");
		}

		return $category;
	}

	/**
	 * Removes a previously registered category. No-op when the id is not registered.
	 */
	public function unregister(string $id) : void{
		unset($this->categories[$id]);
	}

	public function has(string $id) : bool{
		return isset($this->categories[$id]);
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getIds() : array{
		return array_keys($this->categories);
	}
}
