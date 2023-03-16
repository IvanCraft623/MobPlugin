<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\schedule;

class ScheduleBuilder {

	private Schedule $schedule;

	/** @var ActivityTransition[] */
	private array $transitions = [];

	public function __construct(Schedule $schedule) {
		$this->schedule = $schedule;
	}

	public function changeActivityAt(int $time, Activity $activity): self {
		$this->transitions[] = new ActivityTransition($time, $activity);
		return $this;
	}

	public function build(): Schedule {
		foreach ($this->transitions as $activityTransition) {
			$this->schedule->ensureTimelineExistsFor($activityTransition->getActivity());
		}
		foreach ($this->transitions as $a) {
			$activity = $a->getActivity();
			foreach ($this->schedule->getAllTimelinesExceptFor($activity) as $timeline) {
				$timeline->addKeyframe($a->getTime(), 0.0);
			}
			$this->schedule->getTimelineFor($activity)->addKeyframe($a->getTime(), 1.0);
		}
		return $this->schedule;
	}
}