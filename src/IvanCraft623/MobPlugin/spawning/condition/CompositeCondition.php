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

namespace IvanCraft623\MobPlugin\spawning\condition;

use function count;
use function is_bool;

/**
 * A condition over child conditions, reduced by deciding each child it can.
 */
abstract class CompositeCondition implements ReducibleCondition{
	/** @phpstan-param list<SpawnCondition> $conditions */
	final public function __construct(
		protected readonly array $conditions
	){}

	/**
	 * Reduces any condition: a cacheable one to its outcome, a reducible one to what is
	 * left of it, and any other to itself.
	 */
	public static function reduceCondition(SpawnCondition $condition, CacheableConditionContext $ctx) : SpawnCondition|bool{
		if($condition instanceof CacheableCondition){
			return $condition->test($ctx);
		}

		return $condition instanceof ReducibleCondition ? $condition->reduce($ctx) : $condition;
	}

	/**
	 * Drops the children whose outcome is the neutral one, and is decided by the first
	 * child with the other outcome.
	 *
	 * @param bool $decisive the child outcome that decides the whole condition
	 */
	protected function reduceChildren(CacheableConditionContext $ctx, bool $decisive) : SpawnCondition|bool{
		$remaining = [];
		$changed = false;
		foreach($this->conditions as $condition){
			$reduced = self::reduceCondition($condition, $ctx);
			if(is_bool($reduced)){
				if($reduced === $decisive){
					return $decisive;
				}
				$changed = true;
				continue;
			}
			$changed = $changed || $reduced !== $condition;
			$remaining[] = $reduced;
		}
		if(!$changed){
			return $this;
		}

		return match(count($remaining)){
			0 => !$decisive,
			1 => $remaining[0],
			default => new static($remaining),
		};
	}
}