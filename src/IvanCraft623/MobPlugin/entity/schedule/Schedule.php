<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\schedule;

use pocketmine\utils\CloningRegistryTrait;

class Schedule {
	use CloningRegistryTrait;

	private array $timelines = [];

	protected static function register(string $name, Schedule $schedule): void {
		self::_registryRegister($name, $schedule);
	}

	/**
	 * @return Schedule[]
	 */
	public static function getAll(): array {
		/** @var Schedule[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup(): void {
		$empty = (new ScheduleBuilder(new Schedule()))->changeActivityAt(0, Activity::IDLE())->build();
		self::register("empty", $empty);
		$simple = (new ScheduleBuilder(new Schedule()))->changeActivityAt(5000, Activity::WORK())->changeActivityAt(11000, Activity::REST())->build();
		self::register("simple", $empty);
		$villager_baby = (new ScheduleBuilder(new Schedule()))->changeActivityAt(3000, Activity::PLAY())->changeActivityAt(6000, Activity::IDLE())->changeActivityAt(10000, Activity::PLAY())->changeActivityAt(12000, Activity::REST())->build();
		self::register("villager_baby", $villager_baby);
		$villager_default = (new ScheduleBuilder(new Schedule()))->changeActivityAt(10, Activity::IDLE())->changeActivityAt(2000, Activity::WORK())->changeActivityAt(9000, Activity::MEET())->changeActivityAt(11000, Activity::IDLE())->changeActivityAt(12000, Activity::REST())->build();
		self::register("villager_default", $villager_default);
	}

	public function ensureTimelineExistsFor(Activity $activity): void {
		foreach ($this->timelines as $key => $data) {
			if ($activity->equals($data[0])) {
				return;
			}
		}
		$this->timelines[] = [$activity, new Timeline()];
	}

	public function getTimelineFor(Activity $activity): Timeline {
		foreach ($this->timelines as $key => $data) {
			if ($activity->equals($data[0])) {
				return $data[1];
			}
		}
	}

	public function getAllTimelinesExceptFor(Activity $activity): Timeline {
		$timelines = [];
		foreach ($this->timelines as $key => $data) {
			if (!$activity->equals($data[0])) {
				$timelines[] = $data[1];
			}
		}
		return $timelines;
	}

	public function getActivityAt(int $timeStamp): Activity {
		$activity = Activity::IDLE();
		$max = -1;
		foreach ($this->timelines as $key => $data) {
			if ($data[1]->getValueAt($timeStamp) > $max) {
				$activity = $data[0];
			}
		}
		return $activity;
	}
}