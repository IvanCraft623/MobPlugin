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

namespace IvanCraft623\MobPlugin\entity\ai\navigation;

/**
 * What a mob may do to get where it is going. Goals change them while the mob lives.
 */
final class TraversalRules{
	public function __construct(
		public bool $canFloat = false,
		public bool $canOpenDoors = false,
		public bool $canPassDoors = true,
		public bool $canWalkOverFences = false,
		/** Paths stop before the first node in direct sunlight */
		public bool $avoidSun = false,
		/** Whether an amphibious mob swims through open water; otherwise it wades through it */
		public bool $canSwim = true
	){
	}
}