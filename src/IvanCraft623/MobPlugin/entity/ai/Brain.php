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

namespace IvanCraft623\MobPlugin\entity\ai;

use IvanCraft623\MobPlugin\entity\ai\behavior\Behavior;
use IvanCraft623\MobPlugin\entity\ai\behavior\BehaviorStatus;
use IvanCraft623\MobPlugin\entity\ai\memory\ExpirableValue;

use IvanCraft623\MobPlugin\entity\ai\memory\MemoryModuleType;
use IvanCraft623\MobPlugin\entity\ai\memory\MemoryStatus;
use IvanCraft623\MobPlugin\entity\ai\sensing\Sensor;
use IvanCraft623\MobPlugin\entity\Living;
use IvanCraft623\MobPlugin\entity\schedule\Activity;
use IvanCraft623\MobPlugin\entity\schedule\Schedule;
use IvanCraft623\MobPlugin\utils\Pair;
use pocketmine\utils\ObjectSet;
use function count;
use function is_array;

class Brain {

	/**
	 * @var MemoryModuleType[]
	 * @phpstan-var array<string, MemoryModuleType> MemoryModuleType->name() => MemoryModuleType
	 */
	private array $memories = [];

	/** @var Sensor[] */
	private array $sensors = [];

	/**
	 * @var array<int, array<int, Behavior[]>>
	 * array<priority, <array<Activity->id(), Behavior[]>>
	 */
	private array $availableBehaviorsByPriority = [];

	private Schedule $schedule;

	/** @var array<int, Pair<MemoryModuleType, MemoryStatus>[]> activityId => Pair */
	private array $activityRequirements = [];

	/** @var array<int, MemoryModuleType[]> activityId => MemoryModuleType[] */
	private array $activityMemoriesToEraseWhenStopped = [];

	/**
	 * @var Activity[]|ObjectSet
	 * @phpstan-var ObjectSet<Activity>
	 */
	private ObjectSet $coreActivities;

	/**
	 * @var Activity[]|ObjectSet
	 * @phpstan-var ObjectSet<Activity>
	 */
	private ObjectSet $activeActivities;

	private Activity $defaultActivity;

	private int $lastScheduleUpdate;

	/**
	 * @param MemoryModuleType[] $memories
	 * @param Sensor[]           $sensors
	 * @param MemoryValue[]      $memoryValues
	 */
	public function __construct(array $memories, array $sensors, array $memoryValues) {
		$this->schedule = Schedule::EMPTY();
		$this->coreActivities = new ObjectSet();
		$this->activeActivities = new ObjectSet();
		$this->defaultActivity = Activity::IDLE();
		$this->lastScheduleUpdate = -9999;
		foreach ($memories as $memory) {
			$this->memories[$memory->name()] = $memory;
		}
		$this->sensors = $sensors;
		foreach ($sensors as $sensor) {
			foreach ($sensor->requires() as $memory) {
				$this->memories[$memory->name()] = $memory;
			}
		}
		foreach ($memoryValues as $memoryValue) {
			$memoryValue->setMemoryInternal($this);
		}
	}

	/**
	 * @return MemoryValue[]
	 */
	public function memories() : array {
		$values = [];
		foreach ($this->memories as $memory) {
			$values[] = MemoryValue::createUnchecked($memory, $memory->getValue());
		}
		return $values;
	}

	public function hasMemoryValue(MemoryModuleType $type) : bool {
		return $this->checkMemory($type, MemoryStatus::VALUE_PRESENT());
	}

	public function eraseMemory(MemoryModuleType $type) : void {
		$this->setMemory($type, null);
	}

	public function setMemory(MemoryModuleType $type, mixed $value) : void {
		$this->setMemoryInternal($type, new ExpirableValue($value));
	}

	public function setMemoryWithExpiry(MemoryModuleType $type, mixed $value, int $timeToLive) : void {
		$this->setMemoryInternal($type, new ExpirableValue($value, $timeToLive));
	}

	public function setMemoryInternal(MemoryModuleType $memory, ?ExpirableValue $value) : void {
		if (isset($this->memories[$memory->name()])) {
			$isEmptyArray = false;

			if ($value !== null && is_array($value->getValue())) {
				$isEmptyArray = count($value->getValue()) === 0;
			}

			if ($isEmptyArray) {
				$this->eraseMemory($memory);
			} else {
				$memory->setValue($value);
				$this->memories[$memory->name()] = $memory;
			}
		}
	}

	public function getMemory(MemoryModuleType $type) : mixed {
		if (isset($this->memories[$type->name()])) {
			$expirable = $this->memories[$type->name()]->getValue();
			if ($expirable !== null) {
				return $expirable->getValue();
			}
		}
		return null;
	}

	public function isMemoryValue(MemoryModuleType $type, mixed $value) : bool {
		return $this->getMemory($type) === $value;
	}

	public function checkMemory(MemoryModuleType $type, MemoryStatus $status) : bool {
		if (isset($this->memories[$type->name()])) {
			$expirable = $this->memories[$type->name()]->getValue();
			return $expirable !== null && ($status->equals(MemoryStatus::REGISTERED()) || ($status->equals(MemoryStatus::VALUE_PRESENT())) && $expirable->getValue() !== null || ($status->equals(MemoryStatus::VALUE_ABSENT()) && $expirable->getValue() === null));
		}
		return false;
	}

	public function getSchedule() : Schedule {
		return $this->schedule;
	}

	public function setSchedule(Schedule $schedule) : void {
		$this->schedule = $schedule;
	}

	/**
	 * @param Activity[]|ObjectSet $activities
	 * @phpstan-param ObjectSet<Activity> $activities
	 */
	public function setCoreActivities(ObjectSet $activities) : void {
		$this->coreActivities = $activities;
	}

	/**
	 * @return Behavior[]
	 */
	public function getRunningBehaviors() : array {
		$behaviors = [];
		foreach ($this->availableBehaviorsByPriority as $priority => $activities) {
			foreach ($activities as $arctivityId => $behaviors) {
				foreach ($behaviors as $behavior) {
					if ($behavior->getStatus()->equals(BehaviorStatus::RUNNING())) {
						$behaviors[] = $behavior;
					}
				}
			}
		}

		return $behaviors;
	}

	public function useDefaultActivity() : void {
		$this->setActiveActivity($this->defaultActivity);
	}

	public function getActiveNonCoreActivity() : ?Activity {
		foreach ($this->activeActivities->toArray() as $activity) {
			if (!$this->coreActivities->contains($activity)) {
				return $activity;
			}
		}
		return null;
	}

	public function setActiveActivityIfPossible(Activity $activity) : void {
		if ($this->activityRequirementsAreMet($activity)) {
			$this->setActiveActivity($activity);
		} else {
			$this->useDefaultActivity();
		}
	}

	private function setActiveActivity(Activity $activity) : void {
		if ($this->isActive($activity)) {
			return;
		}
		$this->eraseMemoriesForOtherActivitesThan($activity);
		$this->activeActivities->clear();
		foreach ($this->coreActivities->toArray() as $act) {
			$this->activeActivities->add($act);
		}
		$this->activeActivities->add($activity);
	}

	private function eraseMemoriesForOtherActivitesThan(Activity $activity) : void {
		foreach ($this->activeActivities->toArray() as $act) {
			if (!$act->equals($activity)) {
				if (isset($this->activityMemoriesToEraseWhenStopped[$act->id()])) {
					$memories = $this->activityMemoriesToEraseWhenStopped[$act->id()];
					foreach ($memories as $memory) {
						$this->eraseMemory($memory);
					}
				}
			}
		}
	}

	public function updateActivityFromSchedule(int $dayTime, int $gameTime) : void {
		if ($gameTime - $this->lastScheduleUpdate > 20) {
			$this->lastScheduleUpdate = $gameTime;
			$activity = $this->getSchedule()->getActivityAt((int) $dayTime % 24000);
			if (!$this->activeActivities->contains($activity)) {
				$this->setActiveActivityIfPossible($activity);
			}
		}
	}

	/**
	 * @param Activity[]|ObjectSet $activities
	 * @phpstan-param ObjectSet<Activity> $activities
	 */
	public function setActiveActivityToFirstValid(ObjectSet $activities) : void {
		foreach ($activities->toArray() as $activity) {
			if ($this->activityRequirementsAreMet($activity)) {
				$this->setActiveActivity($activity);
				break;
			}
		}
	}

	public function setDefaultActivity(Activity $activity) : void {
		$this->defaultActivity = $activity;
	}

	/**
	 * @param Behavior[] $behaviors
	 */
	public function addActivity(Activity $activity, int $startPriority, array $behaviors) : void {
		$this->addActivityAndRemoveMemoriesWhenStopped($activity, $this->createPriorityPairs($startPriority, $behaviors), [], []);
	}

	/**
	 * @param Pair<int, Behavior>[] $behaviorPairs
	 */
	public function addActivityWithBehaviorPairs(Activity $activity, array $behaviorPairs) : void {
		$this->addActivityAndRemoveMemoriesWhenStopped($activity, $behaviorPairs, [], []);
	}

	/**
	 * @param Behavior[] $behaviors
	 * @param Pair<MemoryModuleType, MemoryStatus>[] $conditions
	 */
	public function addActivityWithConditions(Activity $activity, int $startPriority, array $behaviors, array $conditions) : void {
		$this->addActivityAndRemoveMemoriesWhenStopped($activity, $this->createPriorityPairs($startPriority, $behaviors), $conditions, []);
	}

	/**
	 * @param Pair<int, Behavior>[] $behaviorPairs
	 * @param Pair<MemoryModuleType, MemoryStatus>[] $conditions
	 * @param MemoryModuleType[] $memoryModules
	 */
	public function addActivityAndRemoveMemoriesWhenStopped(Activity $activity, array $behaviorPairs, array $conditions, array $memoryModules) : void {
		$this->activityRequirements[$activity->id()] = $conditions;
		if (count($memoryModules) !== 0) {
			$this->activityMemoriesToEraseWhenStopped[$activity->id()] = $memoryModules;
		}
		foreach ($behaviorPairs as $key => $pair) {
			/** @var int $priority */
			$priority = $pair->getKey();
			/** @var int $priority */
			$behavior = $pair->getValue();

			$activityId = $activity->id();

			$this->availableBehaviorsByPriority[$priority][$activityId][] = $behavior;
		}
	}

	public function isActive(Activity $activity) : bool {
		return $this->activeActivities->contains($activity);
	}

	public function tick(Living $entity) : void {
		$this->forgetOutdatedMemories();
		$this->tickSensors($entity);
		$this->startEachNonRunningBehavior($entity);
		$this->tickEachRunningBehavior($entity);
	}

	private function tickSensors(Living $entity) : void {
		foreach ($this->sensors as $sensor) {
			$sensor->tick($entity);
		}
	}

	private function forgetOutdatedMemories() : void {
		foreach ($this->memories as $memory) {
			$expirable = $memory->getValue();
			if ($expirable !== null) {
				$expirable->tick();
				if ($expirable->hasExpired()) {
					$this->eraseMemory($memory);
				}
			}
		}
	}

	public function stopAll(Living $entity) : void {
		$time = $entity->getWorld()->getServer()->getTick();
		foreach ($this->getRunningBehaviors() as $behavior) {
			$behavior->doStop($entity, $time);
		}
	}

	private function startEachNonRunningBehavior(Living $entity) : void {
		$time = $entity->getWorld()->getServer()->getTick();
		foreach ($this->availableBehaviorsByPriority as $priority => $activities) {
			foreach ($activities as $activityId => $behaviors) {
				foreach ($behaviors as $behavior) {
					if ($behavior->getStatus()->equals(BehaviorStatus::STOPPED())) {
						$behavior->tryStart($entity, $time);
					}
				}
			}
		}
	}

	private function tickEachRunningBehavior(Living $entity) : void {
		$time = $entity->getWorld()->getServer()->getTick();
		foreach ($this->getRunningBehaviors() as $behavior) {
			$behavior->tickOrStop($entity, $time);
		}
	}

	private function activityRequirementsAreMet(Activity $activity) : bool {
		if (!isset($this->activityRequirements[$activity->id()])) {
			return false;
		}
		foreach ($this->activityRequirements[$activity->id()] as $pair) {
			/** @var MemoryModuleType $type */
			$type = $pair->getKey();
			/** @var MemoryStatus $status */
			$status = $pair->getValue();
			if (!$this->checkMemory($type, $status)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param Behavior[] $behaviors
	 *
	 * @return Pair<int, Behavior>[]
	 */
	public static function createPriorityPairs(int $startPriority, array $behaviors) : array {
		$key = $startPriority;
		$pairs = [];
		foreach ($behaviors as $behavior) {
			$pairs[] = new Pair($key++, $behavior);
		}
		return $pairs;
	}
}
