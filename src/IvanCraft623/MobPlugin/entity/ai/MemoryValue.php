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

namespace IvanCraft623\MobPlugin\entity\ai;

use IvanCraft623\MobPlugin\entity\ai\memory\ExpirableValue;
use IvanCraft623\MobPlugin\entity\ai\memory\MemoryModuleType;

final class MemoryValue {

	private MemoryModuleType $type;

	private ?ExpirableValue $value = null;

	public function createUnchecked(MemoryModuleType $type, ?ExpirableValue $value = null) : self {
		return new self($type, $value);
	}

	public function __construct(MemoryModuleType $type, ?ExpirableValue $value = null) {
		$this->type = $type;
		$this->value = $value;
	}

	public function setMemoryInternal(Brain $brain) : void {
		$brain->setMemoryInternal($this->type, $this->value);
	}
}
