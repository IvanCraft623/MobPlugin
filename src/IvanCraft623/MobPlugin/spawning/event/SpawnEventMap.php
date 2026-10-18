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

namespace IvanCraft623\MobPlugin\spawning\event;

/**
 * Which variant of a mob each of its spawn events sets.
 *
 * @phpstan-template TVariant of object
 */
abstract class SpawnEventMap{

	/**
	 * @var object[]
	 * @phpstan-var array<string, TVariant>
	 */
	private array $variants = [];

	/**
	 * @phpstan-param TVariant $variant
	 *
	 * @throws \InvalidArgumentException if the event is already mapped
	 */
	protected function register(string $event, object $variant) : void{
		if(isset($this->variants[$event])){
			throw new \InvalidArgumentException("The spawn event \"$event\" is already mapped");
		}
		$this->variants[$event] = $variant;
	}

	/**
	 * @return object[] event => variant
	 * @phpstan-return array<string, TVariant>
	 */
	public function getAll() : array{
		return $this->variants;
	}
}
