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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\PathfinderMob;

use pocketmine\block\Block;
use pocketmine\world\sound\DoorCrashSound;
use function max;

class BreakDoorGoal extends DoorInteractGoal {

	private int $breakProgress = 0;
	private int $lastBreakProgress = -1;
	private int $maxProgress;

	public function __construct(
		PathfinderMob $entity,
		protected int $minDifficulty,
		int $maxProgress = 240
	) {
		parent::__construct($entity);
		$this->maxProgress = max(240, $maxProgress);
	}

	public function canUse() : bool{
		if (!parent::canUse()) {
			return false;
		}

		//TODO: DO_MOB_GRIEFING gamerule check!

		return $this->entity->getWorld()->getDifficulty() >= $this->minDifficulty && !$this->isDoorOpen();
	}

	public function start() : void{
		parent::start();
		$this->breakProgress = 0;
	}

	public function stop() : void{
		parent::stop();
		//TODO: stop destruction progress
	}

	public function canContinueToUse() : bool{
		return $this->breakProgress <= $this->maxProgress
			&& !$this->isDoorOpen()
			&& $this->doorPosition->distanceSquared($this->entity->getPosition()) < 4.0
			&& $this->entity->getWorld()->getDifficulty() >= $this->minDifficulty;
	}

	public function tick() : void{
		parent::tick();

		if ($this->entity->getRandom()->nextBoundedInt(20) === 0) {
			//$this->entity->getWorld()->addSound($this->doorPosition, new DoorCrashSound()); TODO: register sound!
			$this->entity->doAttackAnimation();
		}

		$this->breakProgress++;
		$i = (int) ($this->breakProgress / $this->maxProgress * 10);
		if ($i !== $this->lastBreakProgress) {
			//TOOD: replace this ai generated code with valid one broadcasting block destruction progress
			//$this->entity->getWorld()->broadcastBlockDestruction($this->entity->getId(), $this->doorPosition, $i);
			$this->lastBreakProgress = $i;
		}

		if ($this->breakProgress === $this->maxProgress && $this->entity->getWorld()->getDifficulty() >= $this->minDifficulty) {
			$world = $this->entity->getWorld();
			$block = $world->getBlock($this->doorPosition);

			$world->useBreakOn($this->doorPosition);
			//TODO: check if any effect need to be bradcasted: sound & particles
		}
	}
}
