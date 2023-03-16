<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\schedule;

class Timeline {

	private array $keyframes = [];

	private int $previousIndex;

	public function addKeyframe(int $timeStamp, float $value): void {
		$this->keyframes[] = new Keyframe($timeStamp, $value);
		$this->sortAndDeduplicateKeyframes();
	}

	private function sortAndDeduplicateKeyframes(): void {
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

	public function getValueAt(int $timeStamp): float {
		if (count($this->keyframes) <= 0) {
			return 0.0;
		}
		$keyframe1 = $this->keyframes[$this->previousIndex];
		$keyframe2 = $this->keyframes[count($this->keyframes) - 1];
		$bool = $timeStamp < $keyframe1->getTimeStamp();
		$int = $bool ? 0 : $this->previousIndex;
		$value = $bool ? $keyframe2->getValue() : $keyframe1->getValue();
		for ($i=$int; $i < count($this->keyframes); ++$i) {
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