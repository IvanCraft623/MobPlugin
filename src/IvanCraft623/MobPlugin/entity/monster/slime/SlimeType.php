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

use pocketmine\utils\EnumTrait;
use function mt_rand;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static SlimeType LARGE()
 * @method static SlimeType MEDIUM()
 * @method static SlimeType SMALL()
 */
final class SlimeType{
	use EnumTrait {
		__construct as Enum___construct;
	}

	protected static function setup() : void{
		self::register(new self("small",
			scale: 1,
			health: 1,
			movementSpeed: 0.3,
			attackDamage: 0,
			dropsGenerator: fn() => [VanillaItems::SLIMEBALL()->setCount(mt_rand(0, 2))]
		));
		self::register(new self("medium",
			scale: 2,
			health: 4,
			movementSpeed: 0.4,
			attackDamage: 2,
			splitType: SlimeType::SMALL()
		));
		self::register(new self("large",
			scale: 4,
			health: 16,
			movementSpeed: 0.6,
			attackDamage: 4,
			splitType: SlimeType::MEDIUM()
		));
	}

	/**
	 * @phpstan-param null|Closure() : Item[] $dropsGenerator
	 */
	private function __construct(
		string $enumName,
		private float $scale,
		private int $health,
		private float $movementSpeed,
		private float $attackDamage,
		private ?SlimeType $splitType = null,
		private ?Closure $dropsGenerator = null
	){
		$this->Enum___construct($enumName);
	}

	public function getScale() : float{
		return $this->scale;
	}

	public function getHealth() : int{
		return $this->health;
	}

	public function getMovementSpeed() : float{
		return $this->movementSpeed;
	}

	public function getAttackDamage() : float{
		return $this->attackDamage;
	}

	public function getSplitType() : ?SlimeType{
		return $this->splitType;
	}

	/**
	 * @phpstan-return null|Closure() : Item[]
	 */
	public function getDropsGenerator() : ?Closure{
		return $this->dropsGenerator;
	}
}
