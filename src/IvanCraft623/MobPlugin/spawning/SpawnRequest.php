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
 * One successful evaluation, handed from the evaluator to the applier: where, which
 * rule set and matched group, and the census counts that passed the gates (so the applier
 * can re-check them against same-pass spawns without rescanning the world).
 */
final class SpawnRequest{
	public function __construct(
		public readonly SpawnPosition $position,
		public readonly SpawnRules $rules,
		public readonly SpawnRuleGroup $group,
		/** Nearby count of the rule set's category in the position's band. */
		public readonly int $categoryCount,
		/** Nearby count of the rule set's identifier in the position's band. */
		public readonly int $densityCount
	){}
}
