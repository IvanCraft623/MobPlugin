<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\lookControl;
use IvanCraft623\MobPlugin\entity\ai\control\JumpControl;
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

	public function getMoveControl(): MoveControl {
		return $this->moveControl;
	}

	public function getJumpControl(): JumpControl {
		return $this->jumpControl;
	}

	public function getSensing(): Sensing {
		return $this->sensing;
	}
	
	public function setXxa(float $xxa): void {
		$this->xxa = $xxa;
	}
	
	public function setYya(float $yya): void {
		$this->yya = $yya;
	}
	
	public function setZza(float $zza): void {
		$this->zza = $zza;
	}

	public function getMaxPitchRot(): int {
		return 40;
	}

	public function getMaxYawRot(): int {
		return 75;
	}

	public function getRotSpeed(): int {
		return 10;
	}
}