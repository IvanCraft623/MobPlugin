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

class WrappedGoal extends Goal {

	private Goal $goal;

	private int $priority;

	private bool $isRunning = false;

	public function __construct(int $priority, Goal $goal) {
		$this->priority = $priority;
		$this->goal = $goal;
	}

	public function canBeReplacedBy(WrappedGoal $goal) : bool{
		return $this->isInterruptable() && $goal->getPriority() < $this->getPriority();
	}

	public function canUse() : bool{
		return $this->goal->canUse();
	}

	public function canContinueToUse() : bool{
		return $this->goal->canContinueToUse();
	}

	public function start() : void{
		if (!$this->isRunning) {
			$this->isRunning = true;
			$this->goal->start();
		}
	}

	public function stop() : void{
		if ($this->isRunning) {
			$this->isRunning = false;
			$this->goal->stop();
		}
	}

	public function tick() : void{
		$this->goal->tick();
	}

	/**
	 * @return int[]
	 */
	public function getFlags() : array{
		return $this->goal->getFlags();
	}

	public function setFlags(int ...$flags) : void{
		$this->goal->setFlags($flags);
	}

	public function requiresUpdateEveryTick() : bool{
		return $this->goal->requiresUpdateEveryTick();
	}

	public function adjustedTickDelay(int $ticks) : int{
		return $this->goal->adjustedTickDelay($ticks);
	}

	public function isRunning() : bool{
		return $this->isRunning;
	}

	public function getPriority() : int{
		return $this->priority;
	}

	public function getGoal() : Goal{
		return $this->goal;
	}

	public function equals(?Goal $goal) : bool{
		if ($goal === $this) {
			return true;
		}
		return $goal instanceof WrappedGoal && $this->goal::class === $goal->goal::class;
	}
}
