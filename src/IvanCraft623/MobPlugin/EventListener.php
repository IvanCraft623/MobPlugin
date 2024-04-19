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

use IvanCraft623\MobPlugin\entity\golem\IronGolem;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\particle\BlockBreakParticle;

class EventListener implements Listener {

	/**
	 * @priority HIGH
	 * @ignoreCancelled
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void {
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
			if ($block->getTypeId() === BlockTypeIds::CARVED_PUMPKIN) {
				MobPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($block) : void{
					$pos = $block->getPosition();
					$patternMatch = IronGolem::getSpawnPattern()->find($pos);
					if ($patternMatch === null) {
						return;
					}

					$world = $pos->getWorld();

					//clear pattern
					$totalWidth = $patternMatch->getWidth();
					for ($currentWidth = 0; $currentWidth < $totalWidth; $currentWidth++) {
						$totalHeight = $patternMatch->getHeight();
						for ($currentHeight = 0; $currentHeight < $totalHeight; $currentHeight++) {
							$b = $patternMatch->getBlock($currentWidth, $currentHeight, 0, $world);
							$p = $b->getPosition();

							$world->addParticle($p->add(0.5, 0.5, 0.5), new BlockBreakParticle($b));
							$world->setBlock($p, VanillaBlocks::AIR());
						}
					}

					//spawn iron golem
					$entity = new IronGolem(Location::fromObject($patternMatch->getBlock(1, 2, 0, $world)->getPosition(), $world));
					$entity->spawnToAll();
				}), 1);
			}
		}
	}
}
