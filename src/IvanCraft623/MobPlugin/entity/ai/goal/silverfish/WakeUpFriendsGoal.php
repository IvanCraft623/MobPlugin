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

namespace IvanCraft623\MobPlugin\entity\ai\goal\silverfish;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\utils\NearestBlockFinder;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\monster\Silverfish;
use IvanCraft623\MobPlugin\event\MobSpawnCause;
use IvanCraft623\MobPlugin\event\MobSpawnEvent;
use IvanCraft623\MobPlugin\memory\Memories;
use IvanCraft623\MobPlugin\memory\Occurrence;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils as PMUtils;

/**
 * While something that hurt it is remembered, breaks the infested blocks around it every second, letting the
 * silverfish inside out.
 */
class WakeUpFriendsGoal extends Goal {

	private const WAKE_UP_INTERVAL = 20; //ticks between wake-ups
	private const SEARCH_RANGE = 10; //blocks, horizontally
	private const SEARCH_HEIGHT = 5; //blocks, up and down

	private int $countdown = 0; //goal ticks until the next wake-up

	public function __construct(
		protected Silverfish $entity
	) {
	}

	public function canUse() : bool{
		$memories = Memories::getInstance();
		if ($memories->latest($this->entity, Occurrence::HURT_BY, Mob::HURT_BY_MEMORY_TICKS)?->getEntity() !== null ||
			$memories->latest($this->entity, Occurrence::HURT_BY_PLAYER, Mob::HURT_BY_MEMORY_TICKS)?->getEntity() !== null
		) {
			if ($this->countdown <= 0) {
				$this->countdown = $this->adjustedTickDelay(self::WAKE_UP_INTERVAL);
			}

			return true;
		}

		return $this->countdown > 0;
	}

	public function tick() : void{
		if ($this->countdown-- > 1) {
			return;
		}

		$world = $this->entity->getWorld();
		$mobGriefing = $this->entity->getSettings()->isMobGriefingEnabled();

		//vanilla scans from the silverfish outwards in a fixed order, this goes nearest first
		foreach (NearestBlockFinder::find(
			$world,
			$this->entity->getPosition(),
			self::SEARCH_RANGE,
			self::SEARCH_HEIGHT,
			...Silverfish::getInfestedTypeIds()
		) as $position) {
			if ($mobGriefing) {
				$world->setBlock($position, VanillaBlocks::AIR());

				$silverfish = new Silverfish(Location::fromObject($position->add(0.5, 0, 0.5), $world, PMUtils::getRandomFloat() * 360));
				(new MobSpawnEvent($silverfish, MobSpawnCause::INFESTED_BLOCK))->call();
				$silverfish->spawnToAll();
			} else {
				//The block can't break, so it just goes back to its normal form
				$host = Silverfish::toggleInfestation($world->getBlock($position)) ?? throw new AssumptionFailedError("Not an infested block");
				$world->setBlock($position, $host);
			}

			if ($this->entity->getRandom()->nextBoolean()) {
				return;
			}
		}
	}
}
