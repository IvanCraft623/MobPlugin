<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai;

use IvanCraft623\MobPlugin\entity\ai\memory\ExpirableValue;
use IvanCraft623\MobPlugin\entity\ai\memory\MemoryModuleType;

final class MemoryValue {

	private MemoryModuleType $type;

	private ?ExpirableValue $value = null;

	public function createUnchecked(MemoryModuleType $type, ?ExpirableValue = null): self {
		return new self($type, $value);
	}

	public function __construct(MemoryModuleType $type, ?ExpirableValue = null) {
		$this->type = $type;
		$this->value = $value;
	}

	public function setMemoryInternal(Brain $brain): void {
		$brain->setMemoryInternal($this->type, $this->value);
	}
}