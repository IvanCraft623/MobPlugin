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

namespace IvanCraft623\MobPlugin\despawning;

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\utils\SimulationRange;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Random;

/**
 * Applies the despawn rules to the entities of every world, every
 * WorldDespawnPass::CHECK_INTERVAL ticks.
 */
final class NaturalDespawnTask extends Task{
	public function __construct(
		private readonly DespawnRuleRegistry $registry,
		private readonly Server $server,
		private readonly Random $random = new Random()
	){}

	public function onRun() : void{
		CustomTimings::$naturalDespawning->startTiming();
		try{
			foreach($this->server->getWorldManager()->getWorlds() as $world){
				(new WorldDespawnPass($world, SimulationRange::getPlayerPositions($world), $this->registry, $this->random))->run();
			}
		}finally{
			CustomTimings::$naturalDespawning->stopTiming();
		}
	}
}
