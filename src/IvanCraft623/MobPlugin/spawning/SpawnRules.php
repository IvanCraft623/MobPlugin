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
use pocketmine\entity\EntitySizeInfo;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * A factory builds the entity without spawning it. Its exceptions are not caught: like any
 * plugin callback, a factory that throws is a bug and stops the server.
 *
 * @phpstan-type SpawnFactory \Closure(World $world, Vector3 $pos, SpawnRuleGroup $group) : Entity
 */
final class SpawnRules{
	/**
	 * @phpstan-param list<SpawnRuleGroup> $groups
	 * @phpstan-param SpawnFactory         $factory
	 * @phpstan-param EntitySizeInfo       $size    the mob's collision box, which must fit where it spawns
	 */
	public function __construct(
		private readonly string $identifier,
		private readonly string $categoryId,
		private readonly array $groups,
		private readonly \Closure $factory,
		private readonly EntitySizeInfo $size
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

	public function getSize() : EntitySizeInfo{
		return $this->size;
	}

	/**
	 * @phpstan-return list<SpawnRuleGroup> every matching group, in rule order
	 */
	public function check(SpawnConditionContext $ctx) : array{
		$matches = [];
		foreach($this->groups as $group){
			if($group->matches($ctx)){
				$matches[] = $group;
			}
		}

		return $matches;
	}
}