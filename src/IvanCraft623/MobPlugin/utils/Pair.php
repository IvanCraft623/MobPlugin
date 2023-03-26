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

namespace IvanCraft623\MobPlugin\utils;

/**
 * @phpstan-template TKey
 * @phpstan-template TValue
 */
class Pair {

	private mixed $key;

	private mixed $value;

	/** @phpstan-param TKey $key */
	/** @phpstan-param TValue $value */
	public function __construct(mixed $key, mixed $value) {
		$this->key = $key;
		$this->value = $value;
	}

	/** @phpstan-return TKey */
	public function getKey() : mixed {
		return $this->key;
	}

	/** @phpstan-return TValue */
	public function getValue() : mixed {
		return $this->value;
	}

	/** @phpstan-param TValue $value */
	public function setValue(mixed $value) : void {
		$this->value = $value;
	}
}
