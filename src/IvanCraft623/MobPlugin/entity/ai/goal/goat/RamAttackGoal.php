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

namespace IvanCraft623\MobPlugin\entity\ai\goal\goat;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\animal\Goat;
use IvanCraft623\MobPlugin\sound\GoatPreRamSound;
use IvanCraft623\MobPlugin\sound\GoatRamImpactSound;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Living;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function floor;
use function max;
use function shuffle;

/**
 * Backs away from its target in a straight line, faces it, waits a second and charges at where it stood,
 * knocking it back. When it misses with a log, stone or ore behind that spot, it drops a horn.
 */
final class RamAttackGoal extends Goal{

	/** @var array<int, true> blocks that snap a horn off, by type id */
	private const HORN_SNAPPING_BLOCKS = [
		BlockTypeIds::OAK_LOG => true,
		BlockTypeIds::SPRUCE_LOG => true,
		BlockTypeIds::BIRCH_LOG => true,
		BlockTypeIds::JUNGLE_LOG => true,
		BlockTypeIds::ACACIA_LOG => true,
		BlockTypeIds::DARK_OAK_LOG => true,
		BlockTypeIds::STONE => true,
		BlockTypeIds::PACKED_ICE => true,
		BlockTypeIds::IRON_ORE => true,
		BlockTypeIds::COAL_ORE => true,
		BlockTypeIds::COPPER_ORE => true,
		BlockTypeIds::EMERALD_ORE => true
	];

	private const ARRIVE_DISTANCE_SQUARED = 2.25; //blocks squared, from the charge's start
	private const MAX_TARGET_DRIFT_SQUARED = 2.25; //blocks squared the target may move before the goat charges
	private const IMPACT_REACH = 1.1; //blocks the goat's box grows toward the spot it charges at
	private const CHARGE_REACHED_DISTANCE_SQUARED = 0.49; //blocks squared from the spot it charges at
	private const RETRY_TICKS = 40; //wait after failing to find or reach a spot
	private const CHARGE_TIMEOUT_TICKS = 140; //ticks before it gives up the charge
	private const TURN_TIMEOUT_TICKS = 20; //ticks it waits to face the target before it goes on anyway
	private const WAIT_TICKS = 20; //ticks it stands facing the target before it charges
	private const KNOCK_TICKS = 2; //ticks after the charge in which it still rams the target

	private int $nextTick = 0; //server tick before which it won't start

	private RamAttackPhase $phase = RamAttackPhase::DONE;

	private bool $charged = false;

	private bool $dropsHorn = false; //whether it loses a horn if the charge hits nothing

	private int $phaseTicks = 0; //ticks in the current phase

	private Vector3 $attackPosition;
	private Vector3 $targetPosition;

	private int $directionX = 0;
	private int $directionZ = 0;

	private ?Living $target = null;

	/**
	 * @param float $runSpeedModifier speed multiplier to the charge's start
	 * @param float $ramSpeedModifier speed multiplier of the charge
	 * @param int   $minRamDistance   blocks from the target the charge may start
	 * @param int   $ramDistance      blocks from the target the charge may start, at most
	 * @param float $knockbackForce   blocks per tick the target is pushed
	 * @param float $knockbackHeight  most blocks per tick it lifts the target
	 */
	public function __construct(
		private Goat $goat,
		private float $runSpeedModifier,
		private float $ramSpeedModifier,
		private int $minRamDistance,
		private int $ramDistance,
		private float $knockbackForce,
		private float $knockbackHeight
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		$target = $this->goat->getTargetEntity();
		if (!$target instanceof Living || !$this->goat->canAttack($target) || $this->goat->getServer()->getTick() < $this->nextTick) {
			return false;
		}

		if (!$this->findAttackPosition($target)) {
			$this->nextTick = $this->goat->getServer()->getTick() + $this->adjustedTickDelay(self::RETRY_TICKS);

			return false;
		}
		$this->target = $target;

		return true;
	}

	public function canContinueToUse() : bool{
		return $this->phase !== RamAttackPhase::DONE && $this->target !== null && $this->goat->canAttack($this->target);
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->goat->onRamStart();
		$this->enterPhase(RamAttackPhase::TAKE_ATTACK_POSITION);
		$this->goat->getNavigation()->moveToXYZ(
			$this->attackPosition->x,
			$this->attackPosition->y,
			$this->attackPosition->z,
			$this->runSpeedModifier
		);
	}

	public function stop() : void{
		$this->goat->getNavigation()->stop();
		$this->goat->setTargetEntity(null);
		$this->target = null;
		$this->phase = RamAttackPhase::DONE;
		if ($this->charged) {
			$this->charged = false;
			$variant = $this->goat->getVariant();
			$this->nextTick = $this->goat->getServer()->getTick() +
				$this->goat->getRandom()->nextRange($variant->getMinRamCooldown(), $variant->getMaxRamCooldown());
		}
	}

	public function tick() : void{
		/** @var Living $target */
		$target = $this->target;
		++$this->phaseTicks;

		match ($this->phase) {
			RamAttackPhase::TAKE_ATTACK_POSITION => $this->tickTakeAttackPosition(),
			RamAttackPhase::TURN_TO_TARGET => $this->tickTurnToTarget($target),
			RamAttackPhase::WAIT => $this->tickWait(),
			RamAttackPhase::CHARGE => $this->tickCharge($target),
			RamAttackPhase::KNOCK_AFTER_CHARGE => $this->tickKnockAfterCharge($target),
			RamAttackPhase::DONE => null
		};
	}

	private function enterPhase(RamAttackPhase $phase) : void{
		$this->phase = $phase;
		$this->phaseTicks = 0;
	}

	// =======================================================================
	// Phase: TAKE_ATTACK_POSITION
	// =======================================================================

	private function tickTakeAttackPosition() : void{
		if ($this->goat->getPosition()->distanceSquared($this->attackPosition) <= self::ARRIVE_DISTANCE_SQUARED) {
			$this->goat->getNavigation()->stop();
			$this->enterPhase(RamAttackPhase::TURN_TO_TARGET);
		} elseif ($this->goat->getNavigation()->isDone()) {
			$this->giveUp();
		}
	}

	// =======================================================================
	// Phase: TURN_TO_TARGET
	// =======================================================================

	private function tickTurnToTarget(Living $target) : void{
		$this->goat->getLookControl()->setLookAt($this->targetPosition);
		if (!$this->goat->getLookControl()->isLookingAtWanted() && $this->phaseTicks < self::TURN_TIMEOUT_TICKS) {
			return;
		}

		if ($target->getPosition()->distanceSquared($this->targetPosition) > self::MAX_TARGET_DRIFT_SQUARED) {
			$this->giveUp();

			return;
		}

		$this->goat->broadcastSound(new GoatPreRamSound($this->goat->getVariant()));
		$this->enterPhase(RamAttackPhase::WAIT);
	}

	// =======================================================================
	// Phase: WAIT
	// =======================================================================

	private function tickWait() : void{
		$this->goat->getLookControl()->setLookAt($this->targetPosition);
		if ($this->phaseTicks >= self::WAIT_TICKS) {
			$this->charged = true;
			$this->dropsHorn = $this->isHornSnappingBehind();
			$this->enterPhase(RamAttackPhase::CHARGE);
		}
	}

	// =======================================================================
	// Phase: CHARGE
	// =======================================================================

	private function tickCharge(Living $target) : void{
		$this->goat->getNavigation()->moveToXYZ(
			$this->targetPosition->x,
			$this->targetPosition->y,
			$this->targetPosition->z,
			$this->ramSpeedModifier,
			0
		);
		if ($this->touchEntities($target)) {
			$this->ram($target);
			$this->phase = RamAttackPhase::DONE;
		} elseif ($this->goat->getPosition()->distanceSquared($this->targetPosition) <= self::CHARGE_REACHED_DISTANCE_SQUARED) {
			$this->goat->getNavigation()->stop();
			$this->enterPhase(RamAttackPhase::KNOCK_AFTER_CHARGE);
		} elseif ($this->phaseTicks >= self::CHARGE_TIMEOUT_TICKS || $this->goat->getNavigation()->isDone()) {
			$this->giveUp();
		}
	}

	// =======================================================================
	// Phase: KNOCK_AFTER_CHARGE
	// =======================================================================

	private function tickKnockAfterCharge(Living $target) : void{
		if ($this->touchEntities($target)) {
			$this->ram($target);
			$this->phase = RamAttackPhase::DONE;
		} elseif ($this->phaseTicks >= self::KNOCK_TICKS) {
			if ($this->dropsHorn) {
				$this->goat->dropHorn($this->targetPosition);
			}
			$this->phase = RamAttackPhase::DONE;
		}
	}

	// =======================================================================
	// Helpers
	// =======================================================================

	private function giveUp() : void{
		$this->phase = RamAttackPhase::DONE;
		$this->nextTick = $this->goat->getServer()->getTick() + $this->adjustedTickDelay(self::RETRY_TICKS);
	}

	/**
	 * Whether the goat's box, grown toward the spot it charges at, reaches the target. Any entity it touches
	 * keeps its horn from snapping off.
	 */
	private function touchEntities(Living $target) : bool{
		$box = $this->goat->getBoundingBox()->addCoord($this->directionX * self::IMPACT_REACH, 0, $this->directionZ * self::IMPACT_REACH);
		$touchedTarget = false;
		foreach ($this->goat->getWorld()->getNearbyEntities($box, $this->goat) as $entity) {
			$this->dropsHorn = false;
			$touchedTarget = $touchedTarget || $entity === $target;
		}

		return $touchedTarget;
	}

	private function ram(Living $target) : void{
		$this->dropsHorn = false;
		$this->goat->attackEntity($target);
		$target->knockBack($this->directionX, $this->directionZ, $this->knockbackForce, $this->knockbackHeight);
		$this->goat->broadcastSound(new GoatRamImpactSound($this->goat->getVariant()));
	}

	/**
	 * Picks the side of the target to charge from: a free, straight lane that ends at a spot the goat can stand on.
	 */
	private function findAttackPosition(Living $target) : bool{
		$random = $this->goat->getRandom();
		$targetPosition = $target->getPosition();
		$x = floor($targetPosition->x) + 0.5;
		$z = floor($targetPosition->z) + 0.5;
		$distance = $this->minRamDistance + $random->nextBoundedInt(max(1, $this->ramDistance - $this->minRamDistance + 1));

		$sides = Facing::HORIZONTAL;
		shuffle($sides);
		foreach ($sides as $side) {
			[$directionX, , $directionZ] = Facing::OFFSET[$side];
			if ($this->isLaneFree($targetPosition->y, $x, $z, $directionX, $directionZ, $distance)) {
				$this->targetPosition = new Vector3($x, $targetPosition->y, $z);
				$this->attackPosition = new Vector3($x - $directionX * $distance, $targetPosition->y, $z - $directionZ * $distance);
				$this->directionX = $directionX;
				$this->directionZ = $directionZ;

				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the block right behind the spot it charges at snaps a horn off.
	 */
	private function isHornSnappingBehind() : bool{
		$block = $this->goat->getWorld()->getBlockAt(
			(int) floor($this->targetPosition->x + $this->directionX),
			(int) floor($this->targetPosition->y),
			(int) floor($this->targetPosition->z + $this->directionZ)
		);

		return isset(self::HORN_SNAPPING_BLOCKS[$block->getTypeId()]);
	}

	private function isLaneFree(float $y, float $x, float $z, int $directionX, int $directionZ, int $distance) : bool{
		for ($step = 1; $step <= $distance; ++$step) {
			if (!$this->goat->canStandAt(new Vector3($x - $directionX * $step, $y, $z - $directionZ * $step))) {
				return false;
			}
		}

		return true;
	}
}
