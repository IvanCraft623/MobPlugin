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

use function asort;
use function count;

class Timeline {

	private array $keyframes = [];

	private int $previousIndex;

	public function addKeyframe(int $timeStamp, float $value) : void {
		$this->keyframes[] = new Keyframe($timeStamp, $value);
		$this->sortAndDeduplicateKeyframes();
	}

	private function sortAndDeduplicateKeyframes() : void {
		$keyframes = [];
		foreach ($this->keyframes as $keyframe) {
			$timeStamp = $keyframe->getTimeStamp();
			if (!isset($keyframes[$timeStamp])) {
				$keyframes[$timeStamp] = $keyframe;
			}
		}
		asort($keyframes);
		$this->keyframes = $keyframes;
		$this->previousIndex = 0;
	}

	public function getValueAt(int $timeStamp) : float {
		if (count($this->keyframes) <= 0) {
			return 0.0;
		}
		$keyframe1 = $this->keyframes[$this->previousIndex];
		$keyframe2 = $this->keyframes[count($this->keyframes) - 1];
		$bool = $timeStamp < $keyframe1->getTimeStamp();
		$int = $bool ? 0 : $this->previousIndex;
		$value = $bool ? $keyframe2->getValue() : $keyframe1->getValue();
		for ($i = $int; $i < count($this->keyframes); ++$i) {
			$keyframe = $this->keyframes[$i];
			if ($keyframe->getTimeStamp() > $timeStamp) {
				break;
			}
			$this->previousIndex = $i;
			$value = $keyframe->getValue();
		}
		return $value;
	}
}
