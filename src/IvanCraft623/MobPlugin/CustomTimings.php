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

namespace IvanCraft623\MobPlugin;

use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;

final class CustomTimings {

	private static bool $initialized = false;

	public static TimingsHandler $entityAiTick;

	public static TimingsHandler $pathfinding;

	public static TimingsHandler $navigation;

	public static TimingsHandler $goalSelector;
	public static TimingsHandler $goalSelectorCleanup;
	public static TimingsHandler $goalSelectorUpdate;
	public static TimingsHandler $goalSelectorTick;

	public static function init() : void{
		if(self::$initialized){
			return;
		}
		self::$initialized = true;

		self::$entityAiTick = new TimingsHandler("Entity AI Tick", group: Timings::GROUP_BREAKDOWN);

		self::$pathfinding = new TimingsHandler("Entity Pathfinding", group: Timings::GROUP_BREAKDOWN);

		self::$navigation = new TimingsHandler("Entity Navigation", group: Timings::GROUP_BREAKDOWN);

		self::$goalSelector = new TimingsHandler("Entity Goal Selector", group: Timings::GROUP_BREAKDOWN);
		self::$goalSelectorCleanup = new TimingsHandler("Entity Goal Selector - Cleanup", self::$goalSelector, group: Timings::GROUP_BREAKDOWN);
		self::$goalSelectorUpdate = new TimingsHandler("Entity Goal Selector - Update", self::$goalSelector, group: Timings::GROUP_BREAKDOWN);
		self::$goalSelectorTick = new TimingsHandler("Entity Goal Selector - Tick", self::$goalSelector, group: Timings::GROUP_BREAKDOWN);
	}
}
