<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\schedule;

class ActivityTransition {

	private int $time;

	private Activity $activity;

	public function __construct(int $time, Activity $activity) {
		$this->time = $time;
		$this->activity = $activity;
	}

	public function getTime(): int {
		return $this->time;
	}

	public function getActivity(): Activity {
		return $this->activity;
	}
}