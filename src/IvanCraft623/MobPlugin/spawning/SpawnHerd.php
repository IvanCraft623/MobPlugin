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
 * One way a group's mobs spawn together. A group with several picks one of them for each
 * spawn, so the members of a herd share its events.
 */
final class SpawnHerd{
	/**
	 * @phpstan-param int         $min               fewest mobs to spawn together
	 * @phpstan-param int         $max               most mobs to spawn together
	 * @phpstan-param string|null $event             spawn event sent to the members from $eventSkipCount on
	 * @phpstan-param string|null $initialEvent      spawn event sent to the first $initialEventCount members
	 */
	public function __construct(
		private readonly int $min = 1,
		private readonly int $max = 1,
		private readonly ?string $event = null,
		private readonly int $eventSkipCount = 0,
		private readonly ?string $initialEvent = null,
		private readonly int $initialEventCount = 0
	){
		if($min < 1 || $max < $min){
			throw new \InvalidArgumentException("Invalid herd size range [$min, $max]");
		}
		if($eventSkipCount < 0 || $initialEventCount < 0){
			throw new \InvalidArgumentException("Event counts must not be negative");
		}
	}

	public function getMin() : int{
		return $this->min;
	}

	public function getMax() : int{
		return $this->max;
	}

	public function getEvent() : ?string{
		return $this->event;
	}

	public function getEventSkipCount() : int{
		return $this->eventSkipCount;
	}

	public function getInitialEvent() : ?string{
		return $this->initialEvent;
	}

	public function getInitialEventCount() : int{
		return $this->initialEventCount;
	}
}