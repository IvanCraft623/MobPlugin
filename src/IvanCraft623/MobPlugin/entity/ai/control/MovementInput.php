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

namespace IvanCraft623\MobPlugin\entity\ai\control;

/**
 * What the controls want a mob to do this tick; Mob::travel() turns it into motion.
 */
final class MovementInput{

	private float $forward = 0.0;

	private float $upward = 0.0;

	private float $sideways = 0.0;

	/** The speed the move control is driving the mob at; read back by the path follower. */
	private float $speed = 0.0;

	public function getForward() : float{
		return $this->forward;
	}

	public function setForward(float $forward) : void{
		$this->forward = $forward;
	}

	public function getUpward() : float{
		return $this->upward;
	}

	public function setUpward(float $upward) : void{
		$this->upward = $upward;
	}

	public function getSideways() : float{
		return $this->sideways;
	}

	public function setSideways(float $sideways) : void{
		$this->sideways = $sideways;
	}

	public function getSpeed() : float{
		return $this->speed;
	}

	/**
	 * Drives the mob forward at $speed.
	 */
	public function walk(float $speed) : void{
		$this->speed = $speed;
		$this->forward = $speed;
	}

	public function isIdle() : bool{
		return $this->forward === 0.0 && $this->upward === 0.0 && $this->sideways === 0.0;
	}

	/**
	 * Horizontal input fades out when the controls stop asking for it.
	 */
	public function decay(float $factor) : void{
		$this->sideways *= $factor;
		$this->forward *= $factor;
	}
}
