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

/** Generic AND combinator; exposes its children so the planner folds metadata through. */
final class AllOf implements SpawnCondition{
	/** @phpstan-param list<SpawnCondition> $conditions */
	public function __construct(
		private readonly array $conditions
	){}

	/**
	 * @phpstan-return list<SpawnCondition>
	 */
	public function getChildren() : array{
		return $this->conditions;
	}

	public function getEvaluationCost() : int{
		// An AND runs every child on the passing path, so its cost is the sum of the
		// children. (A failure short-circuits cheaper, but sum is the right upper bound
		// for scheduling order.)
		$cost = 0;
		foreach($this->conditions as $condition){
			$cost += $condition->getEvaluationCost();
		}

		return $cost;
	}

	public function test(SpawnConditionContext $ctx) : bool{
		foreach($this->conditions as $condition){
			if(!$condition->test($ctx)){
				return false;
			}
		}

		return true;
	}
}
