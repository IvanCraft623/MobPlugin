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
use IvanCraft623\MobPlugin\entity\ai\control\LookControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\goal\GoalSelector;
use IvanCraft623\MobPlugin\entity\ai\navigation\GroundPathNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\ai\sensing\Sensing;
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;

use pocketmine\item\Releasable;
use pocketmine\nbt\tag\CompoundTag;
use function max;

abstract class Mob extends Living {
	//TODO!

	protected PathNavigation $navigation;

	protected LookControl $lookControl;

	protected MoveControl $moveControl;

	protected JumpControl $jumpControl;

	protected GoalSelector $goalSelector;

	protected GoalSelector $targetSelector;

	protected Sensing $sensing;

	/** @var array<int, float> BlockPathTypes->id => malus */
	protected array $pathfindingMalus = [];

	protected float $xxa;

	protected float $yya;

	protected float $zza;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->goalSelector = new GoalSelector();
		$this->targetSelector = new GoalSelector();
		$this->lookControl = new LookControl($this);
		$this->moveControl = new MoveControl($this);
		$this->jumpControl = new JumpControl($this);
		$this->navigation = $this->createNavigation();
		$this->sensing = new Sensing($this);

		$this->registerGoals();
	}

	public function registerGoals() : void{
	}

	public function createNavigation() : PathNavigation{
		return new GroundPathNavigation($this, $this->getWorld());
	}

	public function getMoveControl() : MoveControl {
		return $this->moveControl;
	}

	public function getJumpControl() : JumpControl {
		return $this->jumpControl;
	}

	public function getNavigation() : PathNavigation{
		return $this->navigation;
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

	public function getMaxFallDistance() : int{
		$defaultMax = parent::getMaxFallDistance();
		if ($this->targetId === null) {
			return $defaultMax;
		}

		$maxFallDistance = (int) ($this->getHealth() - $this->getMaxHealth() / 3);
		$maxFallDistance -= (3 - $this->getWorld()->getDifficulty()) * 4;

		return max(0, $maxFallDistance + $defaultMax);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		//TODO: leash check

		/*if ($this->ticksLived % 5 === 0) {
			$this->updateControlFlags();
			$hasUpdate = true;
		}*/

		return $hasUpdate;
	}

	protected function updateControlFlags() : void{
		// TODO!
	}

	public function getPathfindingMalus(BlockPathTypes $pathType) : float{
		// TODO: vehicle checks
		return $this->pathfindingMalus[$pathType->id()] ?? $pathType->getMalus();
	}

	public function setPathfindingMalus(BlockPathTypes $pathType, float $malus) : void{
		$this->pathfindingMalus[$pathType->id()] = $malus;
	}

	public function canUseReleasable(Releasable $item) : bool{
		return false;
	}
}
