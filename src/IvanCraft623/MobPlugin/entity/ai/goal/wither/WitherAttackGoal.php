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

namespace IvanCraft623\MobPlugin\entity\ai\goal\wither;

use IvanCraft623\MobPlugin\entity\ai\control\LookControl;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\boss\Wither;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\utils\Utils as PMUtils;

use function ceil;
use function cos;
use function count;
use function deg2rad;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

class WitherAttackGoal extends Goal {

	public const DEFAULT_MIN_SHOOT_INTERVAL = 5;
	public const DEFAULT_MAX_SHOOT_INTERVAL = 15;

	public const DEFAULT_MIN_MOVE_INTERVAL = 40;
	public const DEFAULT_MAX_MOVE_INTERVAL = 40;

	public const DEFAULT_MAIN_SHOTS_PER_CYCLE = 3;
	public const DEFAULT_SECONDARY_SHOTS_PER_CYCLE = 1;
	public const DEFAULT_THIRD_SHOTS_PER_CYCLE = 1;
	public const DEFAULT_DANGEROUS_SHOTS_PER_CYCLE = 1;

	/** Horizontal radius around the main target used to pick a random fly-to position. */
	public const DEFAULT_MIN_RANDOM_POSITION_RADIUS = 8;
	public const DEFAULT_MAX_RANDOM_POSITION_RADIUS = 25;

	public const DEFAULT_DASH_ATTACK_RANGE = 20;
	public const DEFAULT_DASH_WINDUP_TICKS = 20;
	public const DASH_ATTACK_DAMAGE = 22;
	public const DASH_ATTACK_START_PITCH = 120;
	public const DASH_ATTACK_FINAL_PITCH = 180;

	// Fixed delay (ticks) between the secondary target shot and the main target shot.
	private const SECONDARY_TO_MAIN_DELAY = 5;

	protected int $minShootInterval = self::DEFAULT_MIN_SHOOT_INTERVAL;
	protected int $maxShootInterval = self::DEFAULT_MAX_SHOOT_INTERVAL;

	protected int $minMoveInterval = self::DEFAULT_MIN_MOVE_INTERVAL;
	protected int $maxMoveInterval = self::DEFAULT_MAX_MOVE_INTERVAL;

	protected int $mainShotsPerCycle = self::DEFAULT_MAIN_SHOTS_PER_CYCLE;
	protected int $leftHeadShotsPerCycle = self::DEFAULT_SECONDARY_SHOTS_PER_CYCLE;
	protected int $rightHeadShotsPerCycle = self::DEFAULT_THIRD_SHOTS_PER_CYCLE;
	protected int $dangerousShotsPerCycle = self::DEFAULT_DANGEROUS_SHOTS_PER_CYCLE;

	protected int $minRandomPositionRadius = self::DEFAULT_MIN_RANDOM_POSITION_RADIUS;
	protected int $maxRandomPositionRadius = self::DEFAULT_MAX_RANDOM_POSITION_RADIUS;

	protected float $dashAttackRange = self::DEFAULT_DASH_ATTACK_RANGE;
	protected int   $dashWindupTicks = self::DEFAULT_DASH_WINDUP_TICKS;

	private WitherAttackPhase $phase = WitherAttackPhase::REPOSITIONING;

	/** General-purpose countdown (ticks).*/
	private int $countdown = 0;

	// --- Shooting sub-state ---

	/**
	 * Flat list of shoot actions for the current burst, built once when the
	 * SHOOTING phase starts.
	 * @var WitherBurstShot[]
	 */
	private array $shotQueue = [];

	/** Index into $shotQueue for the next shot to fire. */
	private int $shotQueueIndex = 0;

	/** How many complete bursts have been fired since the goal started (used for dash triggering). */
	private int $burstCount = 0;

	// --- Dash sub-state ---

	/** Position the Wither dashes toward. */
	private ?Vector3 $dashTargetPos = null;

	/** Entities already hit during the current dash (prevents double-damage). */
	private array $damagedEntities = [];

	public function __construct(
		protected Wither $mob,
		protected float $speedModifier,
		protected float $dashSpeedModifier
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool {
		$target = $this->mob->getTargetEntity();
		return $target !== null && $target->isAlive();
	}

	public function start() : void {
		$this->enterRepositioningPhase();
	}

	public function stop() : void {
		$this->mob->getNavigation()->stop();
		$this->phase = WitherAttackPhase::REPOSITIONING;
		$this->countdown = 0;
		$this->shotQueue = [];
		$this->shotQueueIndex = 0;
		$this->burstCount = 0;
		$this->dashTargetPos = null;
		$this->damagedEntities = [];
	}

	public function requiresUpdateEveryTick() : bool {
		return true;
	}

	public function tick() : void {
		$target = $this->mob->getTargetEntity();
		if ($target === null || !$target->isAlive()) {
			return;
		}

		match ($this->phase) {
			WitherAttackPhase::REPOSITIONING => $this->tickRepositioning($target),
			WitherAttackPhase::SHOOTING => $this->tickShooting($target),
			WitherAttackPhase::DASH_WINDUP => $this->tickDashWindup($target),
			WitherAttackPhase::DASHING => $this->tickDashing(),
		};
	}

	// =======================================================================
	// Phase: REPOSITIONING
	// =======================================================================

	private function enterRepositioningPhase() : void {
		$this->phase = WitherAttackPhase::REPOSITIONING;
		$this->countdown = $this->mob->getIntervalTicksByHealth($this->minMoveInterval, $this->maxMoveInterval);
	}

	private function tickRepositioning(Entity $target) : void {
		if ($this->countdown > 0) {
			$this->countdown--;
			return;
		}

		// Powered: skip pathfinding entirely and go straight to shooting.
		if ($this->mob->isPowered()) {
			$this->enterShootingPhase($target);
			return;
		}

		if ($this->countdown === 0) {
			$this->countdown = -1; // sentinel: navigation in progress
			$targetPos = $target->getPosition();
			$angle = PMUtils::getRandomFloat() * M_PI * 2;
			$radius = PMUtils::getRandomFloat() * mt_rand($this->minRandomPositionRadius, $this->maxRandomPositionRadius);
			$this->mob->getNavigation()->moveToXYZ(
				$targetPos->x + $radius * cos($angle),
				$targetPos->y + Wither::HOVER_HEIGHT_ABOVE_TARGET,
				$targetPos->z + $radius * sin($angle),
				$this->speedModifier
			);
			return;
		}

		if (!($navigation = $this->mob->getNavigation())->isPathComputationPending() && $navigation->isDone()) {
			$this->enterShootingPhase($target);
		}
	}

	// =======================================================================
	// Phase: SHOOTING
	// =======================================================================

	private function enterShootingPhase(Entity $mainTarget) : void {
		$this->phase = WitherAttackPhase::SHOOTING;

		$leftHeadTarget = $this->mob->getLeftHeadTargetEntity();
		$rightHeadTarget = $this->mob->getRightHeadTargetEntity();

		// If the third head cannot find a valid target, it reuses the second target.
		if ($rightHeadTarget === null) {
			$rightHeadTarget = $leftHeadTarget;
		}

		$this->shotQueueIndex = 0;
		$this->shotQueue = $this->buildShotQueue($mainTarget, $leftHeadTarget, $rightHeadTarget);
		$this->countdown = $this->mob->getIntervalTicksByHealth($this->minShootInterval, $this->maxShootInterval);
	}

	/**
	 * @return WitherBurstShot[]
	 */
	private function buildShotQueue(Entity $mainTarget, ?Entity $leftHeadTarget, ?Entity $rightHeadTarget) : array {
		$shootInterval = $this->mob->getIntervalTicksByHealth($this->minShootInterval, $this->maxShootInterval);
		$queue = [];

		// Second-target shots (normal skull).
		for ($i = 0; $i < $this->leftHeadShotsPerCycle; $i++) {
			$isLast = ($i === $this->leftHeadShotsPerCycle - 1);
			$queue[] = new WitherBurstShot(
				$leftHeadTarget,
				false,
				$isLast ? self::SECONDARY_TO_MAIN_DELAY : $shootInterval
			);
		}

		// Main shots (normal skull).
		for ($i = 0; $i < $this->mainShotsPerCycle; $i++) {
			$queue[] = new WitherBurstShot(
				$mainTarget,
				false,
				$shootInterval
			);
		}

		// Third-target shots (normal skull).
		for ($i = 0; $i < $this->rightHeadShotsPerCycle; $i++) {
			$isLast = ($i === $this->leftHeadShotsPerCycle - 1);
			$queue[] = new WitherBurstShot(
				$rightHeadTarget,
				false,
				$isLast ? self::SECONDARY_TO_MAIN_DELAY : $shootInterval
			);
		}

		// Dangerous shots (blue skull) at main target — last in burst.
		for ($i = 0; $i < $this->dangerousShotsPerCycle; $i++) {
			$queue[] = new WitherBurstShot(
				$mainTarget,
				true,
				$shootInterval // delay after the last shot (unused but kept consistent)
			);
		}

		return $queue;
	}

	private function tickShooting(Entity $mainTarget) : void {
		$this->countdown--;
		if ($this->countdown > 0) {
			return;
		}

		$this->mob->getLookControl()->setLookAt($mainTarget, 30, 30);

		// Consume all consecutive invalid shots immediately, without burning time on their delays.
		while ($this->shotQueueIndex < count($this->shotQueue)) {
			$shot = $this->shotQueue[$this->shotQueueIndex];
			$shotTarget = $shot->getTargetEntity();

			if ($shotTarget !== null && $this->mob->canAttack($shotTarget)) {
				$this->shotQueueIndex++;
				$this->mob->performRangedAttack($shotTarget, $shot->dangerous ? 1 : 0);
				$this->countdown = $shot->delay;
				return;
			}

			// Invalid target: skip this slot instantly and check the next one.
			$this->shotQueueIndex++;
		}

		$this->onBurstComplete();
	}

	private function onBurstComplete() : void {
		$this->burstCount++;

		if ($this->mob->isPowered() && ($this->burstCount % 2 === 0)) {
			$this->enterDashWindupPhase();
		} else {
			$this->enterRepositioningPhase();
		}
	}

	// =======================================================================
	// Phase: DASH_WINDUP
	// =======================================================================

	private function enterDashWindupPhase() : void {
		$this->phase = WitherAttackPhase::DASH_WINDUP;
		$this->countdown = $this->dashWindupTicks;

		$this->mob->getNavigation()->stop();
		$this->mob->getLookControl()->setResetPitchOnTick(false);
	}

	private function tickDashWindup(Entity $target) : void {
		$location = $this->mob->getLocation();

		$this->countdown--;
		if ($this->countdown <= 0) {
			$this->enterDashingPhase($target);
		}
	}

	// =======================================================================
	// Phase: DASHING
	// =======================================================================

	private function enterDashingPhase(Entity $target) : void {
		$this->phase = WitherAttackPhase::DASHING;
		$this->damagedEntities = [];

		$this->mob->setRotation($this->mob->getLocation()->yaw, self::DASH_ATTACK_START_PITCH);

		// Lock onto the target position.
		$witherPos = $this->mob->getPosition();
		$targetPos = $target->getPosition();

		$dx = $targetPos->x - $witherPos->x;
		$dz = $targetPos->z - $witherPos->z;
		$len = sqrt($dx * $dx + $dz * $dz);

		if ($len > 0) {
			$nx = $dx / $len;
			$nz = $dz / $len;
		} else {
			// Fallback: dash forward along current yaw.
			$yaw = deg2rad($this->mob->getLocation()->yaw);
			$nx = -sin($yaw);
			$nz = cos($yaw);
		}

		$this->dashTargetPos = new Vector3(
			$witherPos->x + $nx * $this->dashAttackRange,
			$witherPos->y,
			$witherPos->z + $nz * $this->dashAttackRange
		);

		// Estimate dash timeout
		$this->countdown = (int) ceil($this->dashAttackRange / ($this->dashSpeedModifier * $this->mob->getFlyingSpeed()));
	}

	private function tickDashing() : void {
		if ($this->dashTargetPos === null) {
			$this->finishDash();
			return;
		}

		$this->mob->breakBlocksAround();
		$this->mob->getMoveControl()->setWantedPosition($this->dashTargetPos, $this->dashSpeedModifier);

		$world = $this->mob->getWorld();
		foreach ($world->getCollidingEntities($this->mob->getBoundingBox(), $this->mob) as $entity) {
			if (!$entity instanceof Living) {
				continue;
			}
			$entityId = $entity->getId();
			if (isset($this->damagedEntities[$entityId])) {
				continue;
			}
			$this->damagedEntities[$entityId] = true;

			$entity->attack(new EntityDamageByEntityEvent(
				$this->mob,
				$entity,
				EntityDamageEvent::CAUSE_ENTITY_ATTACK,
				self::DASH_ATTACK_DAMAGE
			));
		}

		$witherPos = $this->mob->getLocation();
		$dx = $witherPos->x - $this->dashTargetPos->x;
		$dz = $witherPos->z - $this->dashTargetPos->z;

		if (($dx * $dx + $dz * $dz) <= 1) {
			$this->finishDash();
			return;
		}

		// Timeout guard.
		$this->countdown--;
		if ($this->countdown <= 0) {
			$this->finishDash();
			return;
		}

		$location = $this->mob->getLocation();
		if ($this->countdown <= 3) { // Last 3 ticks are used to do a flip
			$this->mob->setRotation($location->yaw, LookControl::rotateTowards(
				$location->pitch,
				self::DASH_ATTACK_FINAL_PITCH,
				20
			));
		} else {
			$this->mob->setRotation($location->yaw, self::DASH_ATTACK_START_PITCH);
		}
	}

	private function finishDash() : void {
		$this->dashTargetPos = null;
		$this->damagedEntities = [];
		$this->mob->setMotion(Vector3::zero());
		$this->mob->getLookControl()->setResetPitchOnTick(true);
		$this->enterRepositioningPhase();
	}

	public function getCurrentDebugInfo() : ?string{
		return $this->phase->name;
	}

	// =======================================================================
	// Getters & Setters
	// =======================================================================

	public function getPhase() : WitherAttackPhase{
		return $this->phase;
	}

	public function getMinShootInterval() : int { return $this->minShootInterval; }

	/**
	 * @return $this
	 */
	public function setMinShootInterval(int $ticks) : self {
		$this->minShootInterval = $ticks;
		return $this;
	}

	public function getMaxShootInterval() : int { return $this->maxShootInterval; }

	/**
	 * @return $this
	 */
	public function setMaxShootInterval(int $ticks) : self {
		$this->maxShootInterval = $ticks;
		return $this;
	}

	public function getMinMoveInterval() : int { return $this->minMoveInterval; }

	/**
	 * @return $this
	 */
	public function setMinMoveInterval(int $ticks) : self {
		$this->minMoveInterval = $ticks;
		return $this;
	}

	public function getMaxMoveInterval() : int { return $this->maxMoveInterval; }

	/**
	 * @return $this
	 */
	public function setMaxMoveInterval(int $ticks) : self {
		$this->maxMoveInterval = $ticks;
		return $this;
	}

	public function getMainShotsPerCycle() : int { return $this->mainShotsPerCycle; }

	/**
	 * @return $this
	 */
	public function setMainShotsPerCycle(int $shots) : self {
		$this->mainShotsPerCycle = $shots;
		return $this;
	}

	public function getLeftHeadShotsPerCycle() : int { return $this->leftHeadShotsPerCycle; }

	/**
	 * @return $this
	 */
	public function setLeftHeadShotsPerCycle(int $shots) : self {
		$this->leftHeadShotsPerCycle = $shots;
		return $this;
	}

	public function getRightHeadShotsPerCycle() : int { return $this->rightHeadShotsPerCycle; }

	/**
	 * @return $this
	 */
	public function setRightHeadShotsPerCycle(int $shots) : self {
		$this->rightHeadShotsPerCycle = $shots;
		return $this;
	}

	public function getDangerousShotsPerCycle() : int { return $this->dangerousShotsPerCycle; }

	/**
	 * @return $this
	 */
	public function setDangerousShotsPerCycle(int $shots) : self {
		$this->dangerousShotsPerCycle = $shots;
		return $this;
	}

	public function getMinRandomPositionRadius() : int { return $this->minRandomPositionRadius; }

	/**
	 * @return $this
	 */
	public function setMinRandomPositionRadius(int $radius) : self {
		$this->minRandomPositionRadius = $radius;
		return $this;
	}

	public function getMaxRandomPositionRadius() : int { return $this->maxRandomPositionRadius; }

	/**
	 * @return $this
	 */
	public function setMaxRandomPositionRadius(int $radius) : self {
		$this->maxRandomPositionRadius = $radius;
		return $this;
	}

	public function getDashAttackRange() : float { return $this->dashAttackRange; }

	/**
	 * @return $this
	 */
	public function setDashAttackRange(float $range) : self {
		$this->dashAttackRange = $range;
		return $this;
	}

	public function getDashWindupTicks() : int { return $this->dashWindupTicks; }

	/**
	 * @return $this
	 */
	public function setDashWindupTicks(int $ticks) : self {
		$this->dashWindupTicks = $ticks;
		return $this;
	}
}
