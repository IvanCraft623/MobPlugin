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
use IvanCraft623\MobPlugin\sound\GoatJumpToBlockSound;
use IvanCraft623\MobPlugin\libs\_9082f0cf01fb9e8d\IvanCraft623\Pathfinder\PathCursor;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use function array_sum;
use function count;
use function intdiv;
use function max;
use function min;
use function shuffle;
use function sqrt;

/**
 * Leaps onto a block that takes a long walk to reach, or can't be walked to.
 *
 * Unlike vanilla, which only tries three launch angles at the speed a drag-free lob needs, it searches the take-off
 * speeds under the goat's real drag, so its leaps land on target and trigger far more often.
 *
 * @phpstan-type TTakeoff array{0: float, 1: list<float>, 2: list<float>}
 */
final class JumpToBlockGoal extends Goal{

	private const PATH_CHECK_TIMEOUT_TICKS = 40; //ticks before it gives up waiting for the path check
	private const FACING_TIMEOUT_TICKS = 20; //ticks it waits to face the destination before it leaps anyway
	private const MIN_AIRBORNE_TICKS = 2; //ticks after the leap before landing counts
	private const AIRBORNE_TIMEOUT_TICKS = 100; //ticks after which the leap is over, landed or not

	private int $nextTick = 0; //server tick before which it won't start

	private JumpToBlockPhase $phase = JumpToBlockPhase::DONE;

	private int $phaseTicks = 0; //ticks in the current phase

	private Vector3 $destination;

	private float $jumpVelocity;
	private float $horizontalVelocity;
	private float $directionX; //unit vector toward the destination
	private float $directionZ;

	/**
	 * @param int   $searchWidth         blocks wide the area is, centred on it
	 * @param int   $searchHeight        blocks tall the area is, centred on it
	 * @param int   $minDistance         least blocks away, horizontally
	 * @param int   $minHeightDifference least blocks above or below it
	 * @param int   $minPathLength       least blocks of walk to the destination, if it can be walked to
	 * @param float $maxVelocity         blocks per tick, the most it takes off or moves sideways with
	 * @param int   $minCooldown         least ticks between attempts
	 * @param int   $maxCooldown         most ticks between attempts
	 */
	public function __construct(
		private Goat $goat,
		private int $searchWidth,
		private int $searchHeight,
		private int $minDistance,
		private int $minHeightDifference,
		private int $minPathLength,
		private float $maxVelocity,
		private int $minCooldown,
		private int $maxCooldown
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		return $this->goat->getServer()->getTick() >= $this->nextTick && $this->goat->isOnGround() && !$this->goat->isInWater();
	}

	public function canContinueToUse() : bool{
		return $this->phase !== JumpToBlockPhase::DONE;
	}

	public function isInterruptable() : bool{
		return false;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->goat->getNavigation()->stop();
		$this->phaseTicks = 0;
		if (!$this->findDestination()) {
			$this->phase = JumpToBlockPhase::DONE;

			return;
		}

		$this->phase = JumpToBlockPhase::CHECK_PATH;
		$this->goat->getNavigation()->requestPathTo($this->destination, 1)->onCompletion(
			fn(PathCursor $cursor) => $this->onPathFound($cursor),
			fn() => $this->onPathChecked(true)
		);
	}

	public function stop() : void{
		$this->phase = JumpToBlockPhase::DONE;
		$this->goat->setLeaping(false);
		//Every attempt costs the cooldown, whether it found something to leap to or not
		$this->nextTick = $this->goat->getServer()->getTick() + $this->goat->getRandom()->nextRange($this->minCooldown, $this->maxCooldown);
	}

	public function tick() : void{
		++$this->phaseTicks;

		match ($this->phase) {
			JumpToBlockPhase::CHECK_PATH => $this->tickCheckPath(),
			JumpToBlockPhase::FACE_JUMP => $this->tickFaceJump(),
			JumpToBlockPhase::AIRBORNE => $this->tickAirborne(),
			JumpToBlockPhase::DONE => null
		};
	}

	private function tickCheckPath() : void{
		if ($this->phaseTicks >= self::PATH_CHECK_TIMEOUT_TICKS) {
			$this->phase = JumpToBlockPhase::DONE;
		}
	}

	private function tickFaceJump() : void{
		$this->goat->getLookControl()->setLookAt($this->destination);
		if ($this->goat->getLookControl()->isLookingAtWanted() || $this->phaseTicks >= self::FACING_TIMEOUT_TICKS) {
			$this->jump();
		}
	}

	private function tickAirborne() : void{
		if (($this->phaseTicks > self::MIN_AIRBORNE_TICKS && $this->goat->isOnGround()) || $this->phaseTicks >= self::AIRBORNE_TIMEOUT_TICKS) {
			$this->phase = JumpToBlockPhase::DONE;
		}
	}

	private function onPathFound(PathCursor $cursor) : void{
		$path = $cursor->getPath();
		$this->onPathChecked(!$path->canReach() || $path->getNodeCount() >= $this->minPathLength);
	}

	/**
	 * @param bool $worthLeaping whether the walk to the destination is long enough, or there is none
	 */
	private function onPathChecked(bool $worthLeaping) : void{
		if ($this->phase !== JumpToBlockPhase::CHECK_PATH) {
			return;
		}

		$this->phase = $worthLeaping ? JumpToBlockPhase::FACE_JUMP : JumpToBlockPhase::DONE;
		$this->phaseTicks = 0;
	}

	private function jump() : void{
		if (!$this->goat->isOnGround()) {
			$this->phase = JumpToBlockPhase::DONE;

			return;
		}

		$this->goat->setLeaping(true);
		$this->goat->setMotion(new Vector3(
			$this->directionX * $this->horizontalVelocity,
			$this->jumpVelocity,
			$this->directionZ * $this->horizontalVelocity
		));
		$this->goat->broadcastSound(new GoatJumpToBlockSound());
		$this->phase = JumpToBlockPhase::AIRBORNE;
		$this->phaseTicks = 0;
	}

	/**
	 * Picks a destination it can leap to.
	 */
	private function findDestination() : bool{
		$position = $this->goat->getPosition();

		$halfHeight = intdiv($this->searchHeight, 2);
		$heightDifferences = [];
		for ($dy = -$halfHeight; $dy <= $halfHeight; ++$dy) {
			if ($dy <= -$this->minHeightDifference || $dy >= $this->minHeightDifference) {
				$heightDifferences[] = $dy;
			}
		}

		$halfWidth = intdiv($this->searchWidth, 2);
		$columns = [];
		for ($dx = -$halfWidth; $dx <= $halfWidth; ++$dx) {
			for ($dz = -$halfWidth; $dz <= $halfWidth; ++$dz) {
				$columns[] = [$dx, $dz];
			}
		}
		shuffle($columns);

		/** @var array<int, list<TTakeoff>> $takeoffs by height difference, they don't depend on the column */
		$takeoffs = [];
		foreach ($columns as [$columnX, $columnZ]) {
			$x = $position->getFloorX() + $columnX + 0.5;
			$z = $position->getFloorZ() + $columnZ + 0.5;
			$dx = $x - $position->x;
			$dz = $z - $position->z;
			$distance = sqrt($dx * $dx + $dz * $dz);
			if ($distance < $this->minDistance) {
				continue;
			}

			shuffle($heightDifferences);
			foreach ($heightDifferences as $dy) {
				$destination = new Vector3($x, $position->getFloorY() + $dy, $z);
				if (!$this->goat->canStandAt($destination)) {
					continue;
				}

				$takeoffs[$dy] ??= $this->getTakeoffs($destination->y - $position->y);
				if ($this->planJump($destination, $distance, $takeoffs[$dy])) {
					$this->destination = $destination;
					$this->directionX = $dx / $distance;
					$this->directionZ = $dz / $distance;

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Every take-off that lands at the height, from the slowest: its velocity, the height on each tick, and the
	 * fraction of the horizontal speed left on each tick.
	 *
	 * @param float $height blocks above the goat to land at
	 *
	 * @return list<TTakeoff>
	 */
	private function getTakeoffs(float $height) : array{
		$position = $this->goat->getPosition();
		//Each tick keeps 1 - drag of the horizontal speed, and the first is also dragged by the floor
		$friction = 1 - $this->goat->getDrag();
		$floorFriction = $this->goat->getWorld()->getBlockAt(
			$position->getFloorX(),
			$position->getFloorY() - 1,
			$position->getFloorZ()
		)->getFrictionFactor();

		$takeoffs = [];
		for ($velocity = $this->goat->getJumpVelocity(); $velocity <= $this->maxVelocity; $velocity += 0.02) {
			$heights = $this->simulateFlight($velocity, $height);
			if ($heights === null) {
				continue;
			}

			$retained = [];
			$factor = 1.0;
			foreach ($heights as $tick => $_) {
				$factor *= $tick === 0 ? $friction * $floorFriction : $friction;
				$retained[] = $factor;
			}
			$takeoffs[] = [$velocity, $heights, $retained];
		}

		return $takeoffs;
	}

	/**
	 * Finds the slowest take-off that lands on the destination without hitting anything.
	 *
	 * @param list<TTakeoff> $takeoffs
	 */
	private function planJump(Vector3 $destination, float $distance, array $takeoffs) : bool{
		$position = $this->goat->getPosition();
		$height = $destination->y - $position->y;
		$dx = ($destination->x - $position->x) / $distance;
		$dz = ($destination->z - $position->z) / $distance;

		foreach ($takeoffs as [$velocity, $heights, $retained]) {
			//A geometric series: the speed that covers the distance follows from its sum
			$horizontalVelocity = $distance / array_sum($retained);
			if ($horizontalVelocity > $this->maxVelocity) {
				continue;
			}

			$travelled = 0.0;
			foreach ($heights as $tick => $flightHeight) {
				$travelled += $horizontalVelocity * $retained[$tick];
				//Checked a bit above the path, so landing isn't a collision
				if (!$this->isClear(
					$position->x + $dx * $travelled,
					$position->y + min($flightHeight, $height) + 0.1,
					$position->z + $dz * $travelled
				)) {
					continue 2;
				}
			}

			$this->jumpVelocity = $velocity;
			$this->horizontalVelocity = $horizontalVelocity;

			return true;
		}

		return false;
	}

	/**
	 * @param float $velocity blocks per tick it takes off with
	 * @param float $height   blocks above the take-off to land at
	 *
	 * @return list<float>|null blocks above the take-off on each tick until it comes down to $height,
	 *                          null if it never does or doesn't clear it by 0.3 blocks
	 */
	private function simulateFlight(float $velocity, float $height) : ?array{
		$gravity = $this->goat->getGravity();
		$friction = 1 - $this->goat->getVerticalDrag();
		$y = 0.0;
		$apex = 0.0;
		$heights = [];
		for ($tick = 1; $tick <= 60; ++$tick) {
			$velocity = ($velocity - $gravity) * $friction;
			$y += $velocity;
			$apex = max($apex, $y);
			$heights[] = $y;
			if ($velocity < 0 && $y <= $height) {
				break;
			}
		}

		return $apex >= $height + 0.3 && $velocity < 0 && $y <= $height ? $heights : null;
	}

	/**
	 * Whether the goat, with its box scaled as it is while leaping, fits there.
	 */
	private function isClear(float $x, float $y, float $z) : bool{
		$size = $this->goat->getSize();
		$halfWidth = $size->getWidth() * Goat::LEAP_BOX_SCALE / 2;
		$box = new AxisAlignedBB(
			$x - $halfWidth,
			$y,
			$z - $halfWidth,
			$x + $halfWidth,
			$y + $size->getHeight() * Goat::LEAP_BOX_SCALE,
			$z + $halfWidth
		);

		return count($this->goat->getWorld()->getCollisionBlocks($box, true)) === 0;
	}
}