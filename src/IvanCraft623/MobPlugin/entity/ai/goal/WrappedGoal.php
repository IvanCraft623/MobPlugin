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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

final class WrappedGoal {

	private bool $isRunning = false;

	public function __construct(
		public readonly int $priority,
		public readonly Goal $goal
	) {
	}

	public function canBeReplacedBy(WrappedGoal $goal) : bool{
		return $goal->priority < $this->priority && $this->goal->isInterruptable();
	}

	public function start() : void{
		if (!$this->isRunning) {
			$this->isRunning = true;
			$this->goal->start();
		}
	}

	public function stop() : void{
		if ($this->isRunning) {
			$this->isRunning = false;
			$this->goal->stop();
		}
	}

	public function isRunning() : bool{
		return $this->isRunning;
	}

	public function getCurrentDebugInfo() : ?string{
		return $this->goal->getCurrentDebugInfo();
	}

	public function destroyCycles() : void{
		$this->stop();
		$this->goal->destroyCycles();
	}
}
