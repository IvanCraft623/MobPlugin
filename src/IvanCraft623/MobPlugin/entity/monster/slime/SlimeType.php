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

namespace IvanCraft623\MobPlugin\entity\monster\slime;

use Closure;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function mt_rand;
use function spl_object_id;

/**
 * @phpstan-type TMetadata array{0: float, 1: int, 2: float, 3: float, 4: ?SlimeType, 5: null|Closure() : Item[]}
 */
enum SlimeType{
	case SMALL;
	case MEDIUM;
	case LARGE;

	/**
	 * Scale, health, movement speed, attack damage, split type and drops generator.
	 *
	 * @phpstan-return TMetadata
	 */
	private function getMetadata() : array{
		/** @phpstan-var array<int, TMetadata> $cache */
		static $cache = [];

		return $cache[spl_object_id($this)] ??= match($this){
			self::SMALL => [1, 1, 0.3, 0, null, static fn() => [VanillaItems::SLIMEBALL()->setCount(mt_rand(0, 2))]],
			self::MEDIUM => [2, 4, 0.4, 2, self::SMALL, null],
			self::LARGE => [4, 16, 0.6, 4, self::MEDIUM, null]
		};
	}

	public function getScale() : float{ return $this->getMetadata()[0]; }

	public function getHealth() : int{ return $this->getMetadata()[1]; }

	public function getDefaultMovementSpeed() : float{ return $this->getMetadata()[2]; }

	public function getAttackDamage() : float{ return $this->getMetadata()[3]; }

	public function getSplitType() : ?SlimeType{ return $this->getMetadata()[4]; }

	/**
	 * @phpstan-return null|Closure() : Item[]
	 */
	public function getDropsGenerator() : ?Closure{ return $this->getMetadata()[5]; }
}