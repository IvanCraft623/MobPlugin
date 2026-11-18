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

use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\monster\Silverfish;

use pocketmine\block\Block;
use pocketmine\math\Facing;
use function count;
use function floor;

/**
 * Now and then, hides in a stone-like block right next to it, leaving the block infested. Strolls otherwise.
 */
class InfestingRandomStrollGoal extends RandomStrollGoal {

	private const INFEST_CHANCE = 10; //1 in this many checks, while it is free to hide

	private ?Block $target = null; //the block it hides in, null if it strolls

	public function canUse() : bool{
		//Busy: no strolling either
		if ($this->entity->getTargetEntity() !== null || !$this->entity->getNavigation()->isDone()) {
			return false;
		}

		$this->target = null;
		if ($this->entity->getSettings()->isMobGriefingEnabled() &&
			$this->entity->getRandom()->nextBoundedInt(self::INFEST_CHANCE) === 0
		) {
			$position = $this->entity->getPosition();
			$offset = Facing::OFFSET[Facing::ALL[$this->entity->getRandom()->nextBoundedInt(count(Facing::ALL))]];
			$block = $this->entity->getWorld()->getBlockAt(
				(int) floor($position->x) + $offset[0],
				(int) floor($position->y + 0.5) + $offset[1],
				(int) floor($position->z) + $offset[2]
			);

			if (isset(Silverfish::getInfestedBlocks()[$block->getTypeId()])) {
				$this->target = $block;

				return true;
			}
		}

		return parent::canUse();
	}

	public function canContinueToUse() : bool{
		return $this->target === null && parent::canContinueToUse();
	}

	public function start() : void{
		if ($this->target === null) {
			parent::start();

			return;
		}

		$world = $this->entity->getWorld();
		$position = $this->target->getPosition();
		$infested = Silverfish::toggleInfestation($world->getBlock($position));
		if ($infested !== null) {
			$world->setBlock($position, $infested);
			$this->entity->flagForDespawn();
		}
	}
}
