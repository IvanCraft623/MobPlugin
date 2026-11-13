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

namespace IvanCraft623\MobPlugin\entity\ai\goal\tame;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\utils\BlockMatcher;
use IvanCraft623\MobPlugin\entity\ai\utils\NearestBlockFinder;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\entity\Pet;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function abs;
use function array_map;
use function max;

/**
 * Walks a tamed pet that isn't sitting to the nearest seat and sits on it, like an order to sit.
 */
class SitOnBlockGoal extends Goal{

	private const REACH_DISTANCE_SQUARED = 1.0; //blocks squared, horizontal
	private const REPATH_INTERVAL = 40; //ticks
	private const GIVE_UP_TICKS = 1200;

	private int $waitTicks = 0;

	private int $travelTicks = 0;

	private Vector3 $target;

	private float $seatY; //top of the target block

	/** @var non-empty-list<BlockMatcher> */
	private array $seats;

	/**
	 * @param non-empty-list<BlockMatcher> $seats          the blocks that can be sat on
	 * @param int                          $searchRange    blocks
	 * @param int                          $searchHeight   blocks up and down
	 * @param int                          $searchInterval ticks between searches, plus up to as many random ones
	 */
	public function __construct(
		private PathfinderMob&Pet $mob,
		private float $speedModifier,
		array $seats,
		private int $searchRange = 8,
		private int $searchHeight = 1,
		private int $searchInterval = 200
	){
		// A seat also needs room for the mob on top of it
		$this->seats = array_map(
			fn(BlockMatcher $seat) => new BlockMatcher(
				$seat->getTypeId(),
				fn(Block $block) => $seat->matches($block) && $this->mob->canStandAt($block->getPosition()->add(0.5, 1, 0.5))
			),
			$seats
		);
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		if (!$this->mob->isTamed() || $this->mob->isSitting()) {
			return false;
		}

		if ($this->waitTicks > 0) {
			--$this->waitTicks;

			return false;
		}
		$this->waitTicks = $this->reducedTickDelay($this->searchInterval + $this->mob->getRandom()->nextBoundedInt($this->searchInterval));

		$seat = NearestBlockFinder::findNearest($this->mob->getWorld(), $this->mob->getPosition(), $this->searchRange, $this->searchHeight, $this->seats);
		if ($seat === null) {
			return false;
		}
		$this->target = $seat->getPosition();
		$this->seatY = $this->target->y;
		foreach ($seat->getCollisionBoxes() as $box) {
			$this->seatY = max($this->seatY, $box->maxY);
		}

		return true;
	}

	public function canContinueToUse() : bool{
		return !$this->mob->isSitting() &&
			$this->travelTicks <= $this->reducedTickDelay(self::GIVE_UP_TICKS) &&
			$this->isValidTarget($this->mob->getWorld(), $this->target);
	}

	public function start() : void{
		$this->travelTicks = 0;
		$this->moveToTarget();
	}

	public function tick() : void{
		++$this->travelTicks;
		$position = $this->mob->getPosition();
		$dx = $this->target->x + 0.5 - $position->x;
		$dz = $this->target->z + 0.5 - $position->z;
		if (($dx ** 2) + ($dz ** 2) <= self::REACH_DISTANCE_SQUARED && abs($this->seatY - $position->y) <= 1) {
			$this->mob->getNavigation()->stop();
			$this->mob->setSitting(true);
			$this->mob->teleport(new Vector3($this->target->x + 0.5, $this->seatY, $this->target->z + 0.5));

			return;
		}

		if ($this->travelTicks % $this->reducedTickDelay(self::REPATH_INTERVAL) === 0) {
			$this->moveToTarget();
		}
	}

	public function stop() : void{
		$this->mob->getNavigation()->stop();
	}

	private function moveToTarget() : void{
		$this->mob->getNavigation()->moveToXYZ($this->target->x + 0.5, $this->seatY, $this->target->z + 0.5, $this->speedModifier);
	}

	private function isValidTarget(World $world, Vector3 $position) : bool{
		$block = $world->getBlock($position);
		foreach ($this->seats as $seat) {
			if ($seat->matches($block)) {
				return true;
			}
		}

		return false;
	}
}
