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

namespace IvanCraft623\MobPlugin\loot;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\item\ItemResolver;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function array_keys;

/**
 * Resolves an item the server lacks to air, so its table still loads and drops nothing for it.
 * A missing variant (data other than 0) stays null, and the item keeps its default variant.
 */
final class AirFallbackItemResolver implements ItemResolver{

	/** @phpstan-var array<string, true> */
	private array $missing = [];

	public function __construct(
		private readonly ItemResolver $inner
	){}

	public function resolve(string $name, int $meta = 0) : ?Item{
		$item = $this->inner->resolve($name, $meta);
		if($item === null && $meta === 0){
			$this->missing[$name] = true;

			return VanillaItems::AIR();
		}

		return $item;
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getMissing() : array{
		return array_keys($this->missing);
	}
}