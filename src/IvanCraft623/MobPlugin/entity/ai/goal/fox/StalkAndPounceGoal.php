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

namespace IvanCraft623\MobPlugin\entity\ai\goal\fox;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\animal\Fox;
use IvanCraft623\MobPlugin\particle\JumpPreventedParticle;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\world\particle\BlockBreakParticle;
use function abs;
use function floor;
use function sqrt;

/**
 * Creeps up on its target, watches it, leaps at it and strikes if it lands close. Landing on snow leaves it stuck.
 *
 * TODO: the leap isn't scaled by the block jump factor (honey).
 */
final class StalkAndPounceGoal extends Goal{

	private const TARGET_LOOK_YAW = 60; //degrees per tick
	private const TARGET_LOOK_PITCH = 30; //degrees per tick
	private const CLEARANCE_SAMPLES = 6; //points along the leap's line that must be free
	private const CLEARANCE_HEIGHT = 3; //blocks above its feet that must be free
	private const MIN_DIRECTION_X = 1.19209e-7; //blocks, smaller x offsets cancel the leap
	private const MIN_LEAP_LENGTH = 0.0001; //blocks, shorter leaps have no direction
	private const STUCK_PARTICLE_CHANCE = 0.2; //out of 1 per tick

	private StalkAndPouncePhase $phase = StalkAndPouncePhase::DONE;

	private int $phaseTicks = 0; //ticks in the current phase

	private bool $pathRequested = false;
	private bool $pathFollowed = false; //whether the last requested path ever started

	private float $maxStalkDistanceSquared; //blocks squared
	private float $maxPounceDistanceSquared; //blocks squared
	private float $strikeDistanceSquared; //blocks squared

	/**
	 * @param float $stalkSpeedModifier speed multiplier while creeping
	 * @param float $maxStalkDistance   blocks from the target it keeps stalking
	 * @param float $leapHeight         blocks per tick the leap lifts it
	 * @param float $leapDistance       blocks per tick the leap pushes it forward
	 * @param float $maxPounceDistance  blocks from the target it stops to watch, and the least it starts from
	 * @param float $strikeDistance     blocks from the target a landing strikes
	 * @param int   $interestTicks      ticks it watches before leaping
	 * @param int   $stuckTicks         ticks it stays stuck
	 * @param int   $stuckBlockTypeId   block type it gets stuck in
	 */
	public function __construct(
		private Fox $fox,
		private float $stalkSpeedModifier,
		float $maxStalkDistance,
		private float $leapHeight,
		private float $leapDistance,
		float $maxPounceDistance,
		float $strikeDistance,
		private int $interestTicks,
		private int $stuckTicks,
		private int $stuckBlockTypeId = BlockTypeIds::SNOW_LAYER
	){
		$this->maxStalkDistanceSquared = $maxStalkDistance ** 2;
		$this->maxPounceDistanceSquared = $maxPounceDistance ** 2;
		$this->strikeDistanceSquared = $strikeDistance ** 2;
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		$target = $this->fox->getTargetEntity();
		if ($target === null || !$this->fox->isOnGround()) {
			return false;
		}

		$distanceSquared = $this->fox->getPosition()->distanceSquared($target->getPosition());

		return $distanceSquared >= $this->maxPounceDistanceSquared && $distanceSquared <= $this->maxStalkDistanceSquared;
	}

	public function canContinueToUse() : bool{
		if ($this->phase === StalkAndPouncePhase::DONE) {
			return false;
		}
		if (!$this->isInterruptable()) {
			return true;
		}

		$target = $this->fox->getTargetEntity();

		return $target !== null && $this->fox->getPosition()->distanceSquared($target->getPosition()) <= $this->maxStalkDistanceSquared;
	}

	public function isInterruptable() : bool{
		return $this->phase !== StalkAndPouncePhase::POUNCING && $this->phase !== StalkAndPouncePhase::STUCK;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->fox->getNavigation()->stop();
		if ($this->fox->getTargetEntity() === null) {
			$this->phase = StalkAndPouncePhase::DONE;

			return;
		}

		$this->enterStalkingPhase();
	}

	public function stop() : void{
		$this->phase = StalkAndPouncePhase::DONE;
		$this->fox->setImmuneToFallDamage(false);
		$this->fox->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::STUNNED, false);
		$this->setStanceFlags();
	}

	public function tick() : void{
		$target = $this->fox->getTargetEntity();
		if ($target === null) {
			$this->phase = StalkAndPouncePhase::DONE;

			return;
		}

		++$this->phaseTicks;
		if ($this->isInterruptable()) {
			$this->fox->getLookControl()->setLookAt($target, self::TARGET_LOOK_YAW, self::TARGET_LOOK_PITCH);
		}

		match ($this->phase) {
			StalkAndPouncePhase::STALKING => $this->tickStalking($target),
			StalkAndPouncePhase::INTERESTED => $this->tickInterested($target),
			StalkAndPouncePhase::POUNCING => $this->tickPouncing($target),
			StalkAndPouncePhase::STUCK => $this->tickStuck(),
			StalkAndPouncePhase::DONE => null
		};
	}

	private function enterPhase(StalkAndPouncePhase $phase) : void{
		$this->phase = $phase;
		$this->phaseTicks = 0;
	}

	// =======================================================================
	// Phase: STALKING
	// =======================================================================

	private function enterStalkingPhase() : void{
		$this->enterPhase(StalkAndPouncePhase::STALKING);
		$this->pathRequested = false;
		$this->pathFollowed = false;
		$this->setStanceFlags(stalking: true);
	}

	private function tickStalking(Entity $target) : void{
		if ($this->fox->getPosition()->distanceSquared($target->getPosition()) <= $this->maxPounceDistanceSquared) {
			$this->enterInterestedPhase();

			return;
		}

		$navigation = $this->fox->getNavigation();
		if (!$navigation->isDone()) {
			$this->pathFollowed = true;

			return;
		}
		if ($this->pathRequested && !$this->pathFollowed) {
			//No path leads to it
			$this->phase = StalkAndPouncePhase::DONE;
			$this->fox->setTargetEntity(null);

			return;
		}

		$position = $target->getPosition();
		$navigation->moveToXYZ($position->x, $position->y, $position->z, $this->stalkSpeedModifier);
		$this->pathRequested = true;
		$this->pathFollowed = false;
	}

	// =======================================================================
	// Phase: INTERESTED
	// =======================================================================

	private function enterInterestedPhase() : void{
		$this->enterPhase(StalkAndPouncePhase::INTERESTED);
		$this->setStanceFlags(interested: true);
		$this->fox->getNavigation()->stop();
	}

	private function tickInterested(Entity $target) : void{
		$navigation = $this->fox->getNavigation();
		if (
			$this->fox->isOnGround() &&
			$this->fox->getPosition()->distanceSquared($target->getPosition()) <= $this->maxPounceDistanceSquared
		) {
			$navigation->stop();
			if ($this->phaseTicks > $this->interestTicks) {
				$this->enterPouncingPhase($target);
			}

			return;
		}

		if ($navigation->isDone()) {
			$this->enterStalkingPhase();
		}
	}

	// =======================================================================
	// Phase: POUNCING
	// =======================================================================

	private function enterPouncingPhase(Entity $target) : void{
		$from = $this->fox->getPosition();
		$to = $target->getPosition();
		$dx = $to->x - $from->x;
		$dy = $to->y - $from->y;
		$dz = $to->z - $from->z;
		if (abs($dx) < self::MIN_DIRECTION_X || !$this->isLeapPathFree($dx, $dz)) {
			$this->phase = StalkAndPouncePhase::DONE;

			return;
		}

		$this->enterPhase(StalkAndPouncePhase::POUNCING);
		$length = sqrt($dx ** 2 + $dy ** 2 + $dz ** 2);
		$this->fox->setMotion($this->fox->getMotion()->add(
			$length < self::MIN_LEAP_LENGTH ? 0 : $dx / $length * $this->leapDistance,
			$this->leapHeight,
			$length < self::MIN_LEAP_LENGTH ? 0 : $dz / $length * $this->leapDistance
		));
		$this->fox->setImmuneToFallDamage(true);
		$this->setStanceFlags();
		$this->splash();
	}

	private function tickPouncing(Entity $target) : void{
		if (!$this->fox->isOnGround() && !$this->fox->isInWater()) {
			return;
		}

		$this->fox->setImmuneToFallDamage(false);
		if ($this->fox->getPosition()->distanceSquared($target->getPosition()) <= $this->strikeDistanceSquared) {
			$this->fox->attackEntity($target);
			$this->phase = StalkAndPouncePhase::DONE;
		} elseif ($this->getBlockAtFeet()->getTypeId() === $this->stuckBlockTypeId) {
			$this->enterStuckPhase();
		} else {
			$this->phase = StalkAndPouncePhase::DONE;
		}
	}

	/**
	 * Splashes when it leaps off a liquid, at its feet or under them.
	 */
	private function splash() : void{
		$world = $this->fox->getWorld();
		$position = $this->fox->getPosition();
		$minY = $this->fox->getBoundingBox()->minY;
		$x = (int) floor($position->x);
		$y = (int) floor($minY);
		$z = (int) floor($position->z);

		$block = $world->getBlockAt($x, $y, $z);
		if (!$block instanceof Liquid && $y >= $minY) {
			$block = $world->getBlockAt($x, $y - 1, $z);
		}
		if ($block instanceof Liquid) {
			$world->addParticle(new Vector3($position->x, $minY, $position->z), new JumpPreventedParticle($block));
		}
	}

	/**
	 * Whether the blocks above its feet are clear along the line to the target.
	 */
	private function isLeapPathFree(float $dx, float $dz) : bool{
		$world = $this->fox->getWorld();
		$from = $this->fox->getPosition();
		$y = (int) floor($from->y);
		for ($i = 0; $i < self::CLEARANCE_SAMPLES; ++$i) {
			$x = (int) floor($from->x + $i / self::CLEARANCE_SAMPLES * $dx);
			$z = (int) floor($from->z + $i / self::CLEARANCE_SAMPLES * $dz);
			for ($height = 1; $height <= self::CLEARANCE_HEIGHT; ++$height) {
				if (!$world->getBlockAt($x, $y + $height, $z)->canBeReplaced()) {
					return false;
				}
			}
		}

		return true;
	}

	// =======================================================================
	// Phase: STUCK
	// =======================================================================

	private function enterStuckPhase() : void{
		$this->enterPhase(StalkAndPouncePhase::STUCK);
		$this->fox->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::STUNNED, true);
		$this->fox->getNavigation()->stop();
		$this->fox->setTargetEntity(null);
	}

	private function tickStuck() : void{
		$this->fox->setMotion($this->fox->getMotion()->withComponents(0, 0, 0));
		if ($this->phaseTicks >= $this->stuckTicks) {
			$this->fox->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::STUNNED, false);
			$this->phase = StalkAndPouncePhase::DONE;

			return;
		}

		if ($this->fox->getRandom()->nextFloat() < self::STUCK_PARTICLE_CHANCE) {
			$this->fox->getWorld()->addParticle($this->fox->getPosition(), new BlockBreakParticle($this->getBlockAtFeet()));
		}
	}

	// =======================================================================
	// Helpers
	// =======================================================================

	private function getBlockAtFeet() : Block{
		$position = $this->fox->getPosition();

		return $this->fox->getWorld()->getBlockAt((int) floor($position->x), (int) floor($position->y), (int) floor($position->z));
	}

	private function setStanceFlags(bool $stalking = false, bool $interested = false) : void{
		$properties = $this->fox->getNetworkProperties();
		$properties->setGenericFlag(EntityMetadataFlags::STALKING, $stalking);
		$properties->setGenericFlag(EntityMetadataFlags::INTERESTED, $interested);
	}
}