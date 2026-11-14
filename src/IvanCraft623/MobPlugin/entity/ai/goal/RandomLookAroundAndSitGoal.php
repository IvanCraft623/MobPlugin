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

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\Sittable;

/**
 * Sits down now and then and looks around a few times, then stands up.
 */
class RandomLookAroundAndSitGoal extends RandomLookAroundGoal{

	private Sittable $sitter;

	private int $looksLeft = 0;

	private int $nextSitTick = 0; //server tick before which it won't sit down

	/**
	 * @param int $minLookCount least looks, the most is one less than $maxLookCount
	 * @param int $maxLookCount see $minLookCount
	 * @param int $cooldown     ticks between standing up and sitting down again
	 *
	 * @see RandomLookAroundGoal for the look parameters
	 */
	public function __construct(
		Mob&Sittable $entity,
		private int $minLookCount,
		private int $maxLookCount,
		int $minLookTime,
		int $maxLookTime,
		float $probability,
		private int $cooldown = 0,
		float $minAngle = -30.0,
		float $maxAngle = 30.0
	){
		parent::__construct($entity, $probability, $minLookTime, $maxLookTime, $minAngle, $maxAngle);
		$this->sitter = $entity;
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return parent::canUse() &&
			!$this->entity->isInWater() &&
			$this->entity->getTargetEntity() === null &&
			!$this->sitter->isSitting() &&
			$this->entity->getServer()->getTick() >= $this->nextSitTick;
	}

	public function canContinueToUse() : bool{
		return $this->looksLeft > 0 && !$this->entity->isInWater() && $this->entity->getTargetEntity() === null;
	}

	public function start() : void{
		parent::start();

		$random = $this->entity->getRandom();
		$this->looksLeft = $this->minLookCount + (
			$this->maxLookCount > $this->minLookCount ? $random->nextBoundedInt($this->maxLookCount - $this->minLookCount) : 0
		);
		$this->sitter->setSitting(true);
	}

	public function stop() : void{
		$this->looksLeft = 0;
		$this->sitter->setSitting(false);
		$this->nextSitTick = $this->entity->getServer()->getTick() + $this->cooldown;
	}

	public function tick() : void{
		parent::tick();

		if ($this->lookTicksLeft <= 0) {
			--$this->looksLeft;
			$this->pickLook();
		}
	}
}
