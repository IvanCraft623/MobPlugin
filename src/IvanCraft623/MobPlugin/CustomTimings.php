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

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\Utils;

final class CustomTimings {

	private static bool $initialized = false;

	public static TimingsHandler $entityAiTick;
	public static TimingsHandler $entityControls;
	public static TimingsHandler $entityTravel;
	public static TimingsHandler $entityPush;

	public static TimingsHandler $naturalSpawning;
	public static TimingsHandler $naturalSpawningSample;
	public static TimingsHandler $naturalSpawningCandidateResolve;
	public static TimingsHandler $naturalSpawningCensus;
	public static TimingsHandler $naturalSpawningSelect;
	public static TimingsHandler $naturalSpawningSpawn;

	public static TimingsHandler $naturalDespawning;

	public static TimingsHandler $navigation;
	public static TimingsHandler $pathRequest;
	public static TimingsHandler $pathBlockChange;

	public static TimingsHandler $goalSelector;
	public static TimingsHandler $goalSelectorCleanup;
	public static TimingsHandler $goalSelectorUpdate;
	public static TimingsHandler $goalSelectorTick;

	/** @var array<class-string<Goal>, TimingsHandler> */
	private static array $goalTimings = [];

	public static function init() : void{
		if(self::$initialized){
			return;
		}
		self::$initialized = true;

		self::$entityAiTick = new TimingsHandler("Entity AI Tick", group: Timings::GROUP_BREAKDOWN);
		self::$entityControls = new TimingsHandler("Entity Controls", group: Timings::GROUP_BREAKDOWN);
		self::$entityTravel = new TimingsHandler("Entity Travel", group: Timings::GROUP_BREAKDOWN);
		self::$entityPush = new TimingsHandler("Entity Push Out Of Entities", group: Timings::GROUP_BREAKDOWN);

		self::$naturalSpawning = new TimingsHandler("Natural Spawning", group: Timings::GROUP_BREAKDOWN);
		self::$naturalSpawningSample = new TimingsHandler("Natural Spawning - Sample", self::$naturalSpawning, group: Timings::GROUP_BREAKDOWN);
		self::$naturalSpawningCandidateResolve = new TimingsHandler("Natural Spawning - Candidate Resolve", self::$naturalSpawning, group: Timings::GROUP_BREAKDOWN);
		self::$naturalSpawningCensus = new TimingsHandler("Natural Spawning - Census", self::$naturalSpawning, group: Timings::GROUP_BREAKDOWN);
		self::$naturalSpawningSelect = new TimingsHandler("Natural Spawning - Select", self::$naturalSpawning, group: Timings::GROUP_BREAKDOWN);
		self::$naturalSpawningSpawn = new TimingsHandler("Natural Spawning - Spawn", self::$naturalSpawning, group: Timings::GROUP_BREAKDOWN);

		self::$naturalDespawning = new TimingsHandler("Natural Despawning", group: Timings::GROUP_BREAKDOWN);

		self::$navigation = new TimingsHandler("Entity Navigation", group: Timings::GROUP_BREAKDOWN);
		self::$pathRequest = new TimingsHandler("Entity Path Request", group: Timings::GROUP_BREAKDOWN);
		self::$pathBlockChange = new TimingsHandler("Entity Path Block Change", group: Timings::GROUP_BREAKDOWN);

		self::$goalSelector = new TimingsHandler("Entity Goal Selector", group: Timings::GROUP_BREAKDOWN);
		self::$goalSelectorCleanup = new TimingsHandler("Entity Goal Selector - Cleanup", self::$goalSelector, group: Timings::GROUP_BREAKDOWN);
		self::$goalSelectorUpdate = new TimingsHandler("Entity Goal Selector - Update", self::$goalSelector, group: Timings::GROUP_BREAKDOWN);
		self::$goalSelectorTick = new TimingsHandler("Entity Goal Selector - Tick", self::$goalSelector, group: Timings::GROUP_BREAKDOWN);
	}

	public static function getGoalTimings(Goal $goal) : TimingsHandler{
		return self::$goalTimings[$goal::class] ??= new TimingsHandler(
			"Entity Goal - " . Utils::getNiceClassName($goal),
			group: Timings::GROUP_BREAKDOWN
		);
	}
}
