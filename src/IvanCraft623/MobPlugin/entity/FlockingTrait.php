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

namespace IvanCraft623\MobPlugin\entity;

/**
 * Implements Flocking apart from the mob-specific methods. Call initFlocking() in registerGoals().
 *
 * @phpstan-require-extends PathfinderMob
 * @phpstan-require-implements Flocking
 */
trait FlockingTrait{

	private FlockMembership $flockMembership;

	protected function initFlocking() : void{
		$this->flockMembership = new FlockMembership($this, $this->getFlockingParameters(), $this->getRandom());
	}

	public function getFlockMembership() : FlockMembership{
		return $this->flockMembership;
	}

	public function canFlockWith(Flocking $other) : bool{
		return $other::class === static::class;
	}

	protected function destroyCycles() : void{
		parent::destroyCycles();

		unset($this->flockMembership);
	}
}