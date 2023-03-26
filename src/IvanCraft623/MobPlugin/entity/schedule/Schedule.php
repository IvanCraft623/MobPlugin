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

use IvanCraft623\MobPlugin\utils\Pair;

use pocketmine\utils\CloningRegistryTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static Schedule EMPTY()
 * @method static Schedule SIMPLE()
 * @method static Schedule VILLAGER_BABY()
 * @method static Schedule VILLAGER_DEFAULT()
 */
final class Schedule {
	use CloningRegistryTrait;

	/**
	 * @var Pair[]
	 * @phpstan-var array<int, Pair<Activity, Timeline>>
	 * array<Activity->id(), Pair<Activity, Timeline>>
	 */
	private array $timelines = [];

	protected static function register(string $name, Schedule $schedule) : void {
		self::_registryRegister($name, $schedule);
	}

	/**
	 * @return Schedule[]
	 */
	public static function getAll() : array {
		/** @var Schedule[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void {
		$empty = (new ScheduleBuilder(new Schedule()))->changeActivityAt(0, Activity::IDLE())->build();
		self::register("empty", $empty);
		$simple = (new ScheduleBuilder(new Schedule()))->changeActivityAt(5000, Activity::WORK())->changeActivityAt(11000, Activity::REST())->build();
		self::register("simple", $empty);
		$villager_baby = (new ScheduleBuilder(new Schedule()))->changeActivityAt(3000, Activity::PLAY())->changeActivityAt(6000, Activity::IDLE())->changeActivityAt(10000, Activity::PLAY())->changeActivityAt(12000, Activity::REST())->build();
		self::register("villager_baby", $villager_baby);
		$villager_default = (new ScheduleBuilder(new Schedule()))->changeActivityAt(10, Activity::IDLE())->changeActivityAt(2000, Activity::WORK())->changeActivityAt(9000, Activity::MEET())->changeActivityAt(11000, Activity::IDLE())->changeActivityAt(12000, Activity::REST())->build();
		self::register("villager_default", $villager_default);
	}

	public function ensureTimelineExistsFor(Activity $activity) : void {
		if (!isset($this->timelines[$activity->id()])) {
			$this->timelines[$activity->id()] = new Pair($activity, new Timeline());
		}
	}

	public function getTimelineFor(Activity $activity) : Timeline {
		return $this->timelines[$activity->id()]->getValue();
	}

	/**
	 * @return Timeline[]
	 */
	public function getAllTimelinesExceptFor(Activity $activity) : array {
		$timelines = [];
		foreach ($this->timelines as $activityId => $pair) {
			if (!$activity->equals($pair->getKey())) {
				$timelines[] = $pair->getValue();
			}
		}
		return $timelines;
	}

	public function getActivityAt(int $timeStamp) : Activity{
		$activity = Activity::IDLE();
		$maxValue = -1;
		foreach ($this->timelines as $pair) {
			$timeline = $pair->getValue();
			$value = $timeline->getValueAt($timeStamp);
			if ($value > $maxValue) {
				$maxValue = $value;
				$activity = $pair->getKey();
			}
		}
		return $activity;
	}
}
