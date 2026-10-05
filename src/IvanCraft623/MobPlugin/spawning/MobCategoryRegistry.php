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

use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaMobCategories;
use pocketmine\utils\SingletonTrait;

final class MobCategoryRegistry{
	use SingletonTrait;

	/** @var array<string, MobCategory> keyed by category id */
	private array $categories = [];

	private function __construct(){
		$this->register(new MobCategory(VanillaMobCategories::MONSTER, 8, 16));
		$this->register(new MobCategory(VanillaMobCategories::ANIMAL, 4, 4));
		$this->register(new MobCategory(VanillaMobCategories::AMBIENT, 0, 2));
		$this->register(new MobCategory(VanillaMobCategories::WATER_ANIMAL, 36, 36));
		$this->register(new MobCategory(VanillaMobCategories::CAT, 4, 0));
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
	 * Removes a previously registered category. No-op when the id is not registered.
	 */
	public function unregister(string $id) : void{
		unset($this->categories[$id]);
	}

	public function has(string $id) : bool{
		return isset($this->categories[$id]);
	}
}