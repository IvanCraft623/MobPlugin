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

use function ceil;

abstract class Goal {

	public const FLAG_MOVE = 0;
	public const FLAG_LOOK = 1;
	public const FLAG_JUMP = 2;
	public const FLAG_TARGET = 3;

	/** @var int[] */
	protected array $flags = [];

	abstract public function canUse() : bool;

	public function canContinueToUse() : bool{
		return $this->canUse();
	}

	public function isInterruptable() : bool{
		return true;
	}

	public function start() : void{
	}

	public function stop() : void{
	}

	public function requiresUpdateEveryTick() : bool{
		return false;
	}

	public function tick() : void{
	}

	/**
	 * @return int[]
	 */
	public function getFlags() : array{
		return $this->flags;
	}

	public function setFlags(int ...$flags) : void{
		$this->flags = $flags;
	}

	public function adjustedTickDelay(int $ticks) : int{
		return $this->requiresUpdateEveryTick() ? $ticks : $this->reducedTickDelay($ticks);
	}

	public function reducedTickDelay(int $ticks) : int{
		return (int) ceil($ticks / 2);
	}

	public function getCurrentDebugInfo() : ?string{
		return null;
	}
}
