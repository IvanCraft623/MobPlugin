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

use IvanCraft623\MobPlugin\entity\ai\control\JumpControl;
use IvanCraft623\MobPlugin\entity\ai\control\lookControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\sensing\Sensing;

use pocketmine\nbt\tag\CompoundTag;

abstract class Mob extends Living {
	//TODO!

	protected MoveControl $lookControl;

	protected MoveControl $moveControl;

	protected JumpControl $jumpControl;

	protected Sensing $sensing;

	protected float $xxa;

	protected float $yya;

	protected float $zza;

	public function __construct(CompoundTag $nbt) {
		parent::__construct($nbt);

		$this->lookControl = new lookControl($this);
		$this->moveControl = new MoveControl($this);
		$this->jumpControl = new JumpControl($this);
		$this->sensing = new Sensing($this);
	}

	public function getMoveControl() : MoveControl {
		return $this->moveControl;
	}

	public function getJumpControl() : JumpControl {
		return $this->jumpControl;
	}

	public function getSensing() : Sensing {
		return $this->sensing;
	}

	public function setXxa(float $xxa) : void {
		$this->xxa = $xxa;
	}

	public function setYya(float $yya) : void {
		$this->yya = $yya;
	}

	public function setZza(float $zza) : void {
		$this->zza = $zza;
	}

	public function getMaxPitchRot() : int {
		return 40;
	}

	public function getMaxYawRot() : int {
		return 75;
	}

	public function getRotSpeed() : int {
		return 10;
	}
}
