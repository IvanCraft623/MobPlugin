<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\utils;

class Pair {

	private mixed $key;

	private mixed $value;

	public function __construct(mixed $key, mixes $value) {
		$this->key = $key;
		$this->value = $value;
	}

	public function getKey(): mixed {
		return $this->key;
	}

	public function getValue(): mixed {
		return $this->value;
	}

	public function setValue(mixed $value): void {
		$this->value = $value;
	}
}