<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai;

use pocketmine\utils\ObjectSet;

use IvanCraft623\MobPlugin\entity\Living;
use IvanCraft623\MobPlugin\entity\ai\behavior\BehaviorStatus;
use IvanCraft623\MobPlugin\entity\ai\memory\MemoryModuleType;
use IvanCraft623\MobPlugin\entity\ai\sensing\Sensor;
use IvanCraft623\MobPlugin\entity\schedule\Activity;
use IvanCraft623\MobPlugin\entity\schedule\Schedule;
use IvanCraft623\MobPlugin\utils\Pair;

class Brain {

	private array $memories = [];

	/** @var Sensor[] */
	private array $sensors = [];

	private array $availableBehaviorsByPriority = [];

    private Schedule $schedule;

	private array $activityRequirements = [];

	private array $activityMemoriesToEraseWhenStopped = [];

	private ObjectSet $coreActivities;

	private ObjectSet $activeActivities;

	private Activity $defaultActivity;

	private int $lastScheduleUpdate;

	/**
	 * @param MemoryModuleType[] $memories
	 * @param Sensor[] $sensors
	 * @param MemoryValue[] $memoryValues
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
	public function memories(): array {
		$values = [];
		foreach ($this->memories as $memory) {
			$values[] = MemoryValue::createUnchecked($memory, $memory->getValue());
		}
		return $values;
	}

	public function hasMemoryValue(MemoryModuleType $type): bool {
		return $this->checkMemory($type, MemoryStatus::VALUE_PRESENT());
	}

	public function eraseMemory(MemoryModuleType $type): void {
		$this->setMemory($type, null);
	}

	public function setMemory(MemoryModuleType $type, mixed $value): void {
		$this->setMemoryInternal($type, new ExpirableValue($value));
	}

	public function setMemoryWithExpiry(MemoryModuleType $type, mixed $value, int $timeToLive): void {
		$this->setMemoryInternal($type, new ExpirableValue($value, $timeToLive));
	}

	public function setMemoryInternal(MemoryModuleType $type, ExpirableValue $value): void {
		if (isset($this->memories[$type->name()])) {
			if ($value->getValue() === null) {
				$this->eraseMemory($type);
			} else {
				$memory->setValue($value);
				$this->memories[$memory->name()] = $memory;
			}
		}
	}

	public function getMemory(MemoryModuleType $type): mixed {
		if (isset($this->memories[$type->name()])) {
			$expirable = $this->memories[$type->name()]->getValue();
			if ($expirable !== null) {
				return $expirable->getValue();
			}
		}
		return null;
	}

	public function isMemoryValue(MemoryModuleType $type, mixed $value): bool {
		return $this->getMemory($type) === $value;
	}

	public function checkMemory(MemoryModuleType $type, MemoryStatus $status): bool {
		if (isset($this->memories[$type->name()])) {
			$expirable = $this->memories[$type->name()]->getValue();
			return $expirable !== null && ($status->equals(MemoryStatus::REGISTERED()) || ($status->equals(MemoryStatus::VALUE_PRESENT())) && $expirable->getValue() !== null || ($status->equals(MemoryStatus::VALUE_ABSENT()) && $expirable->getValue() === null));
		}
		return false;
	}

	public function getSchedule(): Schedule {
		return $this->schedule;
	}

	public function setSchedule(Schedule $schedule): void {
		$this->schedule = $schedule;
	}

	public function setCoreActivities(ObjectSet $activities): void {
		$this->coreActivities = $activities;
	}

	public function getRunningBehaviors(): array {
		$behaviors = [];
		foreach ($this->availableBehaviorsByPriority as $activity => $behaviors) {
			foreach ($behaviors as $behavior) {
				if ($behavior->getStatus()->equals(BehaviorStatus::RUNNING())) {
					$behaviors[] = $behavior;
				}
			}
		}
	}

	public function useDefaultActivity(): void {
		$this->setActiveActivity($this->defaultActivity);
	}

	public function getActiveNonCoreActivity(): ?Activity {
		foreach ($this->activeActivities->toArray() as $activity) {
			if (!$this->coreActivities->contains($activity)) {
				return $activity;
			}
		}
		return null;
	}

	public function setActiveActivityIfPossible(Activity $activity): void {
		if ($this->activityRequirementsAreMet($activity)) {
			$this->setActiveActivity($activity);
		} else {
			$this->useDefaultActivity();
		}
	}

	private function setActiveActivity(Activity $activity): void {
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

	private function eraseMemoriesForOtherActivitesThan(Activity $activity): void {
		foreach ($this->activeActivities->toArray() as $act) {
			if (!$act->equals($activity)) {
				if (isset($this->activityMemoriesToEraseWhenStopped[spl_object_id($act)])) {
					$memories = $this->activityMemoriesToEraseWhenStopped[spl_object_id($act)];
					foreach ($memories->toArray() as $memory) {
						$this->eraseMemory($memory);
					}
				}
			}
		}
	}

	public function updateActivityFromSchedule(int $dayTime, int $gameTime): void {
		if ($gameTime - $this->lastScheduleUpdate > 20) {
			$this->lastScheduleUpdate = $gameTime;
			$activity = $this->getSchedule()->getActivityAt((int)$dayTime % 24000);
			if (!$this->activeActivities->contains($activity)) {
				$this->setActiveActivityIfPossible($activity);
			}
		}
	}

	public function setActiveActivityToFirstValid(ObjectSet $activities): void {
		foreach ($activities->toArray() as $activity) {
			if ($this->activityRequirementsAreMet($activity)) {
				$this->setActiveActivity($activity);
				break;
			}
		}
	}

	public function setDefaultActivity(Activity $activity): void {
		$this->defaultActivity = $activity;
	}

	public function addActivity(Activity $activity, array $pairs): void {
		$this->addActivityAndRemoveMemoriesWhenStopped($activity, $pairs, new ObjectSet());
	}

	public function addActivityWithConditions(Activity $activity, array $behaviorPairs, array $memoryPairs): void {
		$this->addActivityAndRemoveMemoriesWhenStopped($activity, $behaviorPairs, $memoryPairs, new ObjectSet());
	}

	public function addActivityAndRemoveMemoriesWhenStopped(Activity $activity, array $behaviorPairs, array $memoryPairs, ObjectSet $memoryModules): void {
		$this->activityRequirements[$activity->getName()] = $memoryPairs;
		if ($memoryModules->toArray() !== []) {
			$this->activityMemoriesToEraseWhenStopped[spl_object_id($activity)] = $memoryModules;
		}
		foreach ($behaviorPairs as $pair) {
			if (!isset($this->availableBehaviorsByPriority[$activity->getName()][$pair->getKey()])) {
				$this->availableBehaviorsByPriority[$activity->getName()][$pair->getKey()] = $pair->getValue();
			}
		}
	}

	public function isActive(Activity $activity): bool {
		return $this->activeActivities->contains($activity);
	}

	public function tick(Living $entity): void {
		$this->forgetOutdatedMemories();
		$this->tickSensors($entity);
		$this->startEachNonRunningBehavior($entity);
		$this->tickEachRunningBehavior($entity);
	}

	private function tickSensors(Living $entity): void {
		foreach ($this->sensors as $sensor) {
			$sensor->tick($entity);
		}
	}

	private function forgetOutdatedMemories(): void {
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

	public function stopAll(Living $entity): void {
		$time = $entity->getWorld()->getServer()->getTick();
		foreach ($this->getRunningBehaviors() as $behavior) {
			$behaviors->doStop($entity, $time);
		}
	}

	private function startEachNonRunningBehavior(Living $entity): void {
		$time = $entity->getWorld()->getServer()->getTick();
		foreach ($this->availableBehaviorsByPriority as $activity => $behaviors) {
			foreach ($behaviors as $behavior) {
				if ($behavior->getStatus()->equals(BehaviorStatus::STOPPED())) {
					$behavior->tryStart($entity, $time);
				}
			}
		}
	}

	private function tickEachRunningBehavior(Living $entity): void {
		$time = $entity->getWorld()->getServer()->getTick();
		foreach ($this->getRunningBehaviors() as $behavior) {
			$behavior->tickOrStop();
		}
	}

	private function activityRequirementsAreMet(Activity $activity): bool {
		if (!isset($this->activityRequirements[$activity->getName()])) {
			return false;
		}
		foreach ($this->activityRequirements[$activity->getName()] as $pair) {
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
	 * @return Pair[]
	 */
	public static function createPriorityPairs(int $int, array $behaviors): array {
		$key = $int;
		$pairs = [];
		foreach ($behaviors as $behavior) {
			$pairs[] = new Pair($key++, $behavior);
		}
		return $pairs;
	}
}
