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
use IvanCraft623\MobPlugin\sound\DoorBumpSound;
use IvanCraft623\MobPlugin\sound\DoorCrashSound;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use function max;
use function min;
use function round;

class BreakDoorGoal extends DoorInteractGoal {

	public const DEFAULT_MAX_PROGRESS = 240; //in ticks

	private const SIXTEEN_BITS_INT_MAX = 65535;

	private int $breakProgress = 0;
	private int $maxProgress;

	public function __construct(
		PathfinderMob $entity,
		protected int $minDifficulty,
		int $maxProgress = self::DEFAULT_MAX_PROGRESS
	) {
		parent::__construct($entity);
		$this->maxProgress = min(max(self::DEFAULT_MAX_PROGRESS, $maxProgress), self::SIXTEEN_BITS_INT_MAX);
	}

	public function canUse() : bool{
		if (!parent::canUse()) {
			return false;
		}

		if (!$this->entity->getSettings()->isMobGriefingEnabled()) {
			return false;
		}

		return $this->entity->getWorld()->getDifficulty() >= $this->minDifficulty && !$this->isDoorOpen();
	}

	public function start() : void{
		parent::start();
		$this->breakProgress = 0;

		$this->entity->getWorld()->broadcastPacketToViewers($this->doorPosition, LevelEventPacket::create(
			LevelEvent::BLOCK_START_BREAK,
			(int) (self::SIXTEEN_BITS_INT_MAX / $this->maxProgress),
			$this->doorPosition
		));
	}

	public function stop() : void{
		$this->entity->getWorld()->broadcastPacketToViewers(
			$this->doorPosition,
			LevelEventPacket::create(LevelEvent::BLOCK_STOP_BREAK, 0, $this->doorPosition)
		);

		parent::stop();
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
			$this->entity->getWorld()->addSound($this->doorPosition->add(0.5, 0.5, 0.5), new DoorBumpSound());
			$this->entity->doAttackAnimation();
		}

		$this->breakProgress++;
		if ($this->breakProgress >= $this->maxProgress && $this->entity->getWorld()->getDifficulty() >= $this->minDifficulty) {
			$world = $this->entity->getWorld();
			$block = $world->getBlock($this->doorPosition);

			$this->entity->getWorld()->addSound($this->doorPosition->add(0.5, 0.5, 0.5), new DoorCrashSound());
			Utils::destroyBlock($world, $this->doorPosition);
			//$world->useBreakOn(vector: $this->doorPosition, createParticles: true);
		}
	}

	public function getCurrentDebugInfo() : ?string{
		return "BreakProgress: " . round(($this->breakProgress / $this->maxProgress) * 100, 1) . "%";
	}
}
