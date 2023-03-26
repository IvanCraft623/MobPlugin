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

namespace IvanCraft623\MobPlugin\entity\ai\behavior;

use IvanCraft623\MobPlugin\entity\Living;

class Behavior {

	protected array $entryCondition;

	private BehaviorStatus $status;

	private int $endTimestamp;

	private int $minDuration;

	private int $maxDuration;

	public function __construct(array $entryCondition, int $minDuration, int $maxDuration) {
		$this->status = BehaviorStatus::STOPPED();
		$this->minDuration = $minDuration;
		$this->maxDuration = $maxDuration;
		$this->entryCondition = $entryCondition;
	}

	public function getStatus() : BehaviorStatus {
		return $this->status;
	}

	public function tryStart(Living $entity, int $time) : bool {
		if ($this->hasRequiredMemories($entity) && $this->checkExtraStartConditions($entity)) {
			$this->status = BehaviorStatus::RUNNING();
			$duration = $this->minDuration + $entity->getRandom()->nextBoundedInt($this->maxDuration + 1 - $this->minDuration);
			$this->endTimestamp = $time + $duration;
			$this->start($entity, $time);
			return true;
		}
		return false;
	}

	protected function start(Living $entity, int $time) : void {
	}

	public function tickOrStop(Living $entity, int $time) : void {
		if (!$this->timedOut($time) && $this->canStillUse($entity, $time)) {
			$this->tick($entity, $time);
		} else {
			$this->doStop($entity, $time);
		}
	}

	protected function tick(Living $entity, int $time) : void {
	}

	public function doStop(Living $entity, int $time) : void {
		$this->status = BehaviorStatus::STOPPED();
		$this->stop($entity, $time);
	}

	protected function stop(Living $entity, int $time) : void {
	}

	protected function canStillUse(Living $entity, int $time) : bool {
		return false;
	}

	protected function timedOut(int $timestamp) : bool {
		return $timestamp > $this->endTimestamp;
	}

	protected function checkExtraStartConditions(Living $entity) : bool {
		return true;
	}

	protected function hasRequiredMemories(Living $entity) : bool {
		foreach ($this->entryCondition as $key => $data) {
			//Values:
			//$data[0] = MemoryModuleType
			//$data[1] = MemoryStatus
			if (!$entity->getBrain()->checkMemory($data[0], $data[1])) {
				return false;
			}
		}
		return true;
	}
}
