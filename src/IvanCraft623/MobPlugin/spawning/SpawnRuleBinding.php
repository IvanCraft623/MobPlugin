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

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * A rule set bound to the factory that materializes its matches. Factories construct
 * but must not spawn (the applier calls spawnToAll() after herd positioning), and run
 * on the main thread only.
 *
 * @phpstan-type SpawnFactory \Closure(World $world, Vector3 $pos, SpawnConditionMatch $match): Entity
 */
final class SpawnRuleBinding{
	/** @phpstan-param SpawnFactory $factory */
	public function __construct(
		private SpawnRules $rules,
		private \Closure $factory
	){}

	public function getRules() : SpawnRules{
		return $this->rules;
	}

	/** @phpstan-return SpawnFactory */
	public function getFactory() : \Closure{
		return $this->factory;
	}
}
