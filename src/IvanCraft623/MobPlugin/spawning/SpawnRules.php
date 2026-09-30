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

use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * @phpstan-type SpawnFactory \Closure(World $world, Vector3 $pos, SpawnRuleGroup $group) : Entity
 */
final class SpawnRules{
	/**
	 * @phpstan-param list<SpawnRuleGroup> $groups
	 * @phpstan-param SpawnFactory         $factory
	 */
	public function __construct(
		private readonly string $identifier,
		private readonly string $categoryId,
		private readonly array $groups,
		private readonly \Closure $factory
	){}

	public function getIdentifier() : string{
		return $this->identifier;
	}

	public function getCategoryId() : string{
		return $this->categoryId;
	}

	/**
	 * @phpstan-return list<SpawnRuleGroup>
	 */
	public function getGroups() : array{
		return $this->groups;
	}

	/**
	 * @phpstan-return SpawnFactory
	 */
	public function getFactory() : \Closure{
		return $this->factory;
	}

	public function check(SpawnConditionContext $ctx) : ?SpawnRuleGroup{
		foreach($this->groups as $group){
			if($group->matches($ctx)){
				return $group;
			}
		}

		return null;
	}
}
