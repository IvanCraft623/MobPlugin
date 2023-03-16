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

namespace IvanCraft623\MobPlugin\entity\schedule;

class ScheduleBuilder {

	private Schedule $schedule;

	/** @var ActivityTransition[] */
	private array $transitions = [];

	public function __construct(Schedule $schedule) {
		$this->schedule = $schedule;
	}

	public function changeActivityAt(int $time, Activity $activity) : self {
		$this->transitions[] = new ActivityTransition($time, $activity);
		return $this;
	}

	public function build() : Schedule {
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
