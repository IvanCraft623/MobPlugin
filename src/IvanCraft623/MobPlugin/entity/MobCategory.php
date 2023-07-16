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

use pocketmine\utils\EnumTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static MobCategory AMBIENT()
 * @method static MobCategory AXOLOTLS()
 * @method static MobCategory CREATURE()
 * @method static MobCategory MISC()
 * @method static MobCategory MONSTER()
 * @method static MobCategory UNDERGROUND_WATER_CREATURE()
 * @method static MobCategory WATER_AMBIENT()
 * @method static MobCategory WATER_CREATURE()
 */
final class MobCategory {
	use EnumTrait {
		__construct as Enum___construct;
	}

	protected static function setup() : void{
		self::registerAll(
			new self("monster", 70, false, false, 64),
			new self("creature", 10, true, true, 64),
			new self("ambient", 15, true, false, 64),
			new self("axolotls", 5, true, false, 64),
			new self("underground_water_creature", 5, true, false, 64),
			new self("water_creature", 5, true, false, 64),
			new self("water_ambient", 20, true, false, 40),
			new self("misc", -1, true, true, 64)
		);
	}

	private function __construct(
		string $enumName,
		private int $max,
		private bool $isFriendly,
		private bool $isPersistent,
		private int $despawnDistance
	){
		$this->Enum___construct($enumName);
	}

	public function getMaxInstancesPerChunk() : int{
		return $this->max;
	}

	public function isFriendly() : bool{
		return $this->isFriendly;
	}

	public function isPersistent() : bool{
		return $this->isPersistent;
	}

	public function getDespawnDistance() : int{
		return $this->despawnDistance;
	}

	public function getNoDespawnDistance() : int{
		return 32;
	}
}
