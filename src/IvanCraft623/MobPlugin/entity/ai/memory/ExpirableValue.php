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

namespace IvanCraft623\MobPlugin\entity\ai\memory;

class ExpirableValue {

	private mixed $value;

	private ?int $timeToLive;

	public function __construct(mixed $object, ?int $timeToLive = null) {
		$this->object = $object;
		$this->timeToLive = $timeToLive;
	}

	public function tick() : void {
		if ($this->canExpire()) {
			--$this->timeToLive;
		}
	}

	public function getValue() : mixed {
		return $this->value;
	}

	public function hasExpired() : bool {
		if ($this->canExpire()) {
			return $this->timeToLive <= 0;
		}
		return false;
	}

	public function canExpire() : bool {
		return $this->timeToLive !== null;
	}
}
