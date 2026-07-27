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

use function array_values;
use function count;
use function ksort;

class Timeline {

	/**
	 * @var Keyframe[]
	 * @phpstan-var array<int, Keyframe>  timestamp => Keyframe
	 */
	private array $keyframes = [];

	/** @var Keyframe[] ordered by timestamp */
	private array $sortedKeyframes = [];

	private int $previousIndex = 0;

	public function addKeyframe(int $timeStamp, float $value) : void {
		$this->keyframes[$timeStamp] = new Keyframe($timeStamp, $value);
		$this->sortAndDeduplicateKeyframes();
	}

	private function sortAndDeduplicateKeyframes() : void {
		ksort($this->keyframes);

		$this->sortedKeyframes = array_values($this->keyframes);
		$this->previousIndex = 0;
	}

	public function getValueAt(int $timeStamp) : float {
		$count = count($this->sortedKeyframes);
		if ($count === 0) {
			return 0.0;
		}

		$keyframe1 = $this->sortedKeyframes[$this->previousIndex];
		$lastKeyframe = $this->sortedKeyframes[$count - 1];

		$searchFromStart = $timeStamp < $keyframe1->getTimeStamp();
		$startIndex = $searchFromStart ? 0 : $this->previousIndex;
		$value = $searchFromStart ? $lastKeyframe->getValue() : $keyframe1->getValue();

		for ($i = $startIndex; $i < $count; ++$i) {
			$keyframe = $this->sortedKeyframes[$i];
			if ($keyframe->getTimeStamp() > $timeStamp) {
				break;
			}
			$this->previousIndex = $i;
			$value = $keyframe->getValue();
		}

		return $value;
	}
}