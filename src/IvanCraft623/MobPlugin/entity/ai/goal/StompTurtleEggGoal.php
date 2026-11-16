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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\block\ExtraBlockTypeIds;
use IvanCraft623\MobPlugin\entity\ai\utils\NearestBlockFinder;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\particle\EggBreakParticle;
use IvanCraft623\MobPlugin\particle\EggPunchParticle;
use IvanCraft623\MobPlugin\sound\TurtleEggAttackSound;
use IvanCraft623\MobPlugin\sound\TurtleEggBreakSound;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use function floor;

/**
 * Walks to a turtle egg and stomps on it until it breaks, eggs and all.
 */
class StompTurtleEggGoal extends MoveToBlockGoal {

	private const ARRIVAL_SLACK = 1.0; //blocks past the goal radius it still steps on to the egg from, by hand
	private const STEP_IN_TICKS = 40; //ticks it tries to step on to the egg by hand, at most
	private const HOP_INTERVAL = 6; //ticks between hops
	private const HOP_VELOCITY = 0.3; //blocks per tick, up on the hop and down halfway to the next
	private const BREAK_TICKS = 60; //ticks of stomping before the egg breaks

	private Vector3 $egg;

	private Block $eggBlock; //the egg as it was stomped on

	private int $stompTicks = 0; //ticks it has stomped

	private int $stepInTicks = 0; //ticks it has tried to step on to the egg

	private bool $finished = false; //whether it broke the egg, or gave up

	/**
	 * @param float $goalRadius blocks from the middle of the egg, across, it has to get to before it steps on to it
	 */
	public function __construct(
		PathfinderMob $entity,
		protected float $speedModifier = 1.0,
		protected float $goalRadius = 1.14,
		int $searchInterval = 20,
		protected int $searchRange = 10,
		protected int $searchHeight = 2
	) {
		parent::__construct($entity, $searchInterval);
	}

	protected function canSearch() : bool{
		return $this->entity->getSettings()->isMobGriefingEnabled();
	}

	protected function findBlock() : bool{
		foreach (NearestBlockFinder::find(
			$this->entity->getWorld(),
			$this->entity->getPosition(),
			$this->searchRange,
			$this->searchHeight,
			ExtraBlockTypeIds::TURTLE_EGG()
		) as $position) {
			$this->egg = $position;

			return true;
		}

		return false;
	}

	public function canContinueToUse() : bool{
		return !$this->finished && $this->isEgg();
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->stompTicks = 0;
		$this->stepInTicks = 0;
		$this->entity->getNavigation()->moveToXYZ($this->egg->x + 0.5, $this->egg->y, $this->egg->z + 0.5, $this->speedModifier);
	}

	public function stop() : void{
		$this->entity->getNavigation()->stop();
		$this->finished = false;

		//It was stomping and the egg is gone, by the stomp or by a trample
		if ($this->stompTicks > 0 && !$this->isEgg()) {
			$world = $this->entity->getWorld();
			$world->addParticle($this->egg, new EggBreakParticle($this->eggBlock));
			$world->addSound($this->egg->add(0.5, 0.5, 0.5), new TurtleEggBreakSound());
		}
	}

	public function tick() : void{
		if ($this->isOverEgg()) {
			$this->stomp();
		} else {
			$this->approach();
		}
	}

	/**
	 * Hops on the egg, and breaks it once it has hopped for long enough.
	 */
	private function stomp() : void{
		$world = $this->entity->getWorld();
		$this->eggBlock = $world->getBlock($this->egg);
		$center = $this->egg->add(0.5, 0.5, 0.5);

		$tick = $this->stompTicks++;
		$hop = $tick % self::HOP_INTERVAL;
		if ($hop === 0 && $this->entity->isOnGround()) {
			$this->entity->setMotion(new Vector3(0, self::HOP_VELOCITY, 0));
		} elseif ($hop === self::HOP_INTERVAL / 2) {
			$this->entity->setMotion(new Vector3(0, -self::HOP_VELOCITY, 0));
		}
		if ($tick > 0) {
			$world->addParticle($this->egg, new EggPunchParticle($this->eggBlock));
		}
		if ($hop === 0) {
			$world->addSound($center, new TurtleEggAttackSound());
		}

		if ($tick > self::BREAK_TICKS) {
			$world->setBlock($this->egg, VanillaBlocks::AIR());
			$this->finished = true;
		}
	}

	/**
	 * A path ends beside an egg it can't walk through, so the last step on to it is by hand. Gives up when it has
	 * landed with no path left and the egg out of reach.
	 */
	private function approach() : void{
		$navigation = $this->entity->getNavigation();
		if (!$this->entity->isOnGround() || $navigation->isPathComputationPending() || !$navigation->isDone()) {
			return;
		}

		$position = $this->entity->getPosition();
		$center = $this->egg->add(0.5, 0, 0.5);
		$distance = ($center->x - $position->x) ** 2 + ($center->z - $position->z) ** 2;
		if (++$this->stepInTicks > self::STEP_IN_TICKS || $distance > ($this->goalRadius + self::ARRIVAL_SLACK) ** 2) {
			$this->finished = true;

			return;
		}

		$this->entity->getMoveControl()->setWantedPosition($center, $this->speedModifier);
	}

	private function isEgg() : bool{
		return $this->entity->getWorld()->getBlock($this->egg)->getTypeId() === ExtraBlockTypeIds::TURTLE_EGG();
	}

	/**
	 * Whether it is over the egg's block, on it or a hop above it.
	 */
	private function isOverEgg() : bool{
		$position = $this->entity->getPosition();
		$height = (int) floor($position->y) - (int) $this->egg->y;

		return (int) floor($position->x) === (int) $this->egg->x &&
			(int) floor($position->z) === (int) $this->egg->z &&
			$height >= 0 && $height <= 1;
	}
}
