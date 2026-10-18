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

use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use pocketmine\entity\Entity;

/**
 * Where a mob stands in the herd an event is sent for.
 */
final class SpawnEventContext{
	/**
	 * @phpstan-param list<Entity> $herd every member created so far
	 */
	public function __construct(
		private readonly array $herd,
		private readonly int $index,
		private readonly SpawnRuleGroup $group
	){}

	/**
	 * @phpstan-return list<Entity>
	 */
	public function getHerd() : array{
		return $this->herd;
	}

	/**
	 * The mob's position in the herd.
	 */
	public function getIndex() : int{
		return $this->index;
	}

	public function getGroup() : SpawnRuleGroup{
		return $this->group;
	}
}
