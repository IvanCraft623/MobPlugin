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

/**
 * A mob a herd member may spawn as, with the event it is created with.
 */
final class SpawnPermutation{
	public function __construct(
		private readonly string $identifier,
		private readonly int $weight,
		private readonly ?string $event = null
	){
		if($weight < 0){
			throw new \InvalidArgumentException("Permutation weight of \"$identifier\" must not be negative, got $weight");
		}
	}

	public function getIdentifier() : string{
		return $this->identifier;
	}

	public function getWeight() : int{
		return $this->weight;
	}

	public function getEvent() : ?string{
		return $this->event;
	}
}