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

/**
 * One spawn condition: an immutable predicate over one spawn attempt. Composition uses
 * the AllOf / AnyOf / Not combinators. Implementations must be immutable plain data;
 * runtime capabilities are captured at parse time, never looked up from the context.
 */
interface SpawnCondition{

	/**
	 * Relative cost of one evaluation, starting at 1 (lowest/cheapest). Lower costs are
	 * scheduled first when a SpawnConditionGroup reorders its AND-list, so a group fails
	 * fast without paying for its priciest checks first. The scale is only relative —
	 * integers, never zero or negative.
	 */
	public function getEvaluationCost() : int;

	public function test(SpawnConditionContext $ctx) : bool;
}
