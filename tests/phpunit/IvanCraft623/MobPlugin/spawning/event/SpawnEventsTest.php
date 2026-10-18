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

namespace IvanCraft623\MobPlugin\spawning\event;

use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Squid;
use function array_merge;
use function array_values;
use function dirname;
use function in_array;

/**
 * Catches a spawn event that was renamed or removed in the pinned data, which at runtime
 * would just stop firing.
 */
final class SpawnEventsTest extends TestCase{
	/**
	 * @phpstan-return array<string, list<string>> identifier => the events its rules send
	 */
	private static function readEvents() : array{
		BiomeTagMap::setInstance(new BiomeTagMap([]));
		$parsed = SpawnRulesParser::createVanilla()->parseFile(dirname(__DIR__, 6) . "/resources/spawning/spawn_rules.json");

		$events = [];
		foreach($parsed as $identifier => [, $groups]){
			$events[$identifier] = [];
			foreach($groups as $group){
				$events[$identifier] = array_merge($events[$identifier], $group->getEvents());
			}
		}

		return $events;
	}

	public function testEveryImplementedMobsEventIsHandled() : void{
		$registry = new SpawnEventRegistry();
		$events = self::readEvents();

		$unhandled = [];
		$mobs = MobPlugin::ALL_ENTITIES;
		$mobs[] = Squid::class;
		foreach($mobs as $mob){
			$identifier = $mob::getNetworkTypeId();
			foreach($events[$identifier] ?? [] as $event){
				if(!$registry->isHandled($event)){
					$unhandled[] = "$identifier sends $event";
				}
			}
		}

		self::assertSame([], $unhandled, "Spawn events nothing handles");
	}

	public function testBuiltInHandlersAreForEventsTheDataSends() : void{
		$sent = array_merge(...array_values(self::readEvents()));
		foreach((new SpawnEventRegistry())->getHandledEvents() as $event){
			self::assertTrue(in_array($event, $sent, true), "$event is handled but no spawn rule sends it");
		}
	}
}
