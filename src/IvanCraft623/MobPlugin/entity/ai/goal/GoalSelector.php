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

use IvanCraft623\MobPlugin\CustomTimings;
use function array_filter;
use function ksort;

class GoalSelector {

	/** @var array<int, WrappedGoal> flag => WrappedGoal */
	protected array $lockedFlags = [];

	/** @var WrappedGoal[] */
	protected array $availableGoals = [];

	/**
	 * Goals started and not yet seen stopped, in the order of $availableGoals.
	 *
	 * @var WrappedGoal[]
	 */
	private array $runningGoals = [];

	/** Bit set of the disabled flags */
	protected int $disabledFlags = 0;

	public function addGoal(int $priority, Goal $goal) : WrappedGoal{
		return $this->availableGoals[] = new WrappedGoal($priority, $goal);
	}

	public function removeGoal(Goal $goal) : void {
		foreach ($this->availableGoals as $key => $wrappedGoal) {
			if ($wrappedGoal->goal === $goal) {
				$wrappedGoal->stop();
				unset($this->availableGoals[$key], $this->runningGoals[$key]);
			}
		}
	}

	public function tick() : void{
		CustomTimings::$goalSelectorCleanup->startTiming();

		foreach ($this->runningGoals as $key => $wrappedGoal) {
			if ($wrappedGoal->isRunning() && (
				($wrappedGoal->goal->getFlagMask() & $this->disabledFlags) !== 0 ||
				!$wrappedGoal->goal->canContinueToUse()
			)) {
				$wrappedGoal->stop();
			}
			if (!$wrappedGoal->isRunning()) {
				unset($this->runningGoals[$key]);
			}
		}

		$lockedFlags = 0;
		foreach ($this->lockedFlags as $flag => $wrappedGoal) {
			if ($wrappedGoal->isRunning()) {
				$lockedFlags |= 1 << $flag;
			} else {
				unset($this->lockedFlags[$flag]);
			}
		}

		CustomTimings::$goalSelectorCleanup->stopTiming();

		CustomTimings::$goalSelectorUpdate->startTiming();

		$started = false;
		foreach ($this->availableGoals as $key => $wrappedGoal) {
			if ($wrappedGoal->isRunning()) {
				continue;
			}

			$goal = $wrappedGoal->goal;
			$flags = $goal->getFlagMask();
			if (($flags & $this->disabledFlags) !== 0) {
				continue;
			}
			if (($flags & $lockedFlags) !== 0) {
				foreach ($this->lockedFlags as $flag => $holder) {
					if (($flags & (1 << $flag)) !== 0 && !$holder->canBeReplacedBy($wrappedGoal)) {
						continue 2;
					}
				}
			}
			if (!$goal->canUse()) {
				continue;
			}

			foreach ($goal->getFlags() as $flag) {
				if (isset($this->lockedFlags[$flag])) {
					$this->lockedFlags[$flag]->stop();
				}
				$this->lockedFlags[$flag] = $wrappedGoal;
			}
			$lockedFlags |= $flags;

			$wrappedGoal->start();
			$this->runningGoals[$key] = $wrappedGoal;
			$started = true;
		}

		if ($started) {
			ksort($this->runningGoals);
		}

		CustomTimings::$goalSelectorUpdate->stopTiming();

		$this->tickRunningGoals(true);
	}

	public function tickRunningGoals(bool $force = false) : void{
		CustomTimings::$goalSelectorTick->startTiming();

		foreach ($this->runningGoals as $wrappedGoal) {
			if ($wrappedGoal->isRunning() && ($force || $wrappedGoal->goal->requiresUpdateEveryTick())) {
				$wrappedGoal->goal->tick();
			}
		}

		CustomTimings::$goalSelectorTick->stopTiming();
	}

	/**
	 * @return WrappedGoal[]
	 */
	public function getAvailableGoals() : array{
		return $this->availableGoals;
	}

	/**
	 * @return WrappedGoal[]
	 */
	public function getRunningGoals() : array{
		return array_filter($this->runningGoals, static function(WrappedGoal $wrappedGoal) : bool{
			return $wrappedGoal->isRunning();
		});
	}

	public function disableControlFlag(int $flag) : void{
		if ($flag < Goal::FLAG_MOVE || $flag > Goal::FLAG_TARGET) {
			throw new \InvalidArgumentException("Invalid goal flag");
		}
		$this->disabledFlags |= 1 << $flag;
	}

	public function enableControlFlag(int $flag) : void{
		if ($flag < Goal::FLAG_MOVE || $flag > Goal::FLAG_TARGET) {
			throw new \InvalidArgumentException("Invalid goal flag");
		}
		$this->disabledFlags &= ~(1 << $flag);
	}

	public function setControlFlag(int $flag, bool $enabled) : void{
		if ($enabled) {
			$this->enableControlFlag($flag);
		} else {
			$this->disableControlFlag($flag);
		}
	}

	public function destroyCycles() : void{
		foreach($this->availableGoals as $wrappedGoal){
			$wrappedGoal->destroyCycles();
		}
		$this->availableGoals = [];
		$this->runningGoals = [];
		$this->lockedFlags = [];
	}
}
