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

use IvanCraft623\MobPlugin\entity\ai\utils\BlockMatcher;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\block\Block;
use function array_map;

/**
 * Walks to a block with something to eat and eats it, leaving the block to grow again.
 */
class RaidGardenGoal extends MoveToBlockGoal {

	private const ARRIVAL_SLACK = 1.0; //blocks past the goal radius where a finished path still counts as arrived

	private ?Block $target = null;

	private int $eatTimer = 0; //ticks before it eats

	private bool $finished = false; //whether it ate, or gave up

	/**
	 * @param non-empty-list<EatableBlock<*>> $eatables
	 * @param float $goalRadius      blocks, how close it has to get to eat
	 * @param int   $initialEatDelay ticks it waits at the block before eating
	 */
	public function __construct(
		PathfinderMob $entity,
		protected float $speedModifier,
		private array $eatables,
		protected int $searchRange = 16,
		protected int $searchHeight = 2,
		protected float $goalRadius = 1.0,
		private int $initialEatDelay = 0
	) {
		parent::__construct($entity);
	}

	protected function canSearch() : bool{
		return true; //TODO: only when the mobGriefing rule is on
	}

	protected function findBlock() : bool{
		$this->target = BlockMatcher::find(
			$this->entity->getWorld(),
			$this->entity->getPosition(),
			$this->searchRange,
			$this->searchHeight,
			...array_map(static fn(EatableBlock $eatable) => $eatable->getMatcher(), $this->eatables)
		)->current();

		return $this->target !== null;
	}

	public function canContinueToUse() : bool{
		return !$this->finished &&
			$this->target !== null &&
			$this->getEaten($this->entity->getWorld()->getBlock($this->target->getPosition())) !== null;
	}

	public function start() : void{
		if ($this->target !== null) {
			$position = $this->target->getPosition();
			$this->entity->getNavigation()->moveToXYZ($position->x + 0.5, $position->y, $position->z + 0.5, $this->speedModifier);
		}
		$this->eatTimer = $this->initialEatDelay;
	}

	public function stop() : void{
		$this->entity->getNavigation()->stop();
		$this->target = null;
		$this->finished = false;
	}

	public function tick() : void{
		if ($this->target === null) {
			return;
		}

		$targetPosition = $this->target->getPosition();
		$position = $this->entity->getPosition();
		$dx = $targetPosition->x + 0.5 - $position->x;
		$dz = $targetPosition->z + 0.5 - $position->z;
		$distanceSquared = ($dx ** 2) + ($dz ** 2);
		if ($distanceSquared > $this->goalRadius ** 2) {
			//A path can't end inside a block it avoids walking through, so having got as near as it can counts too
			$arrived = $this->entity->getNavigation()->isDone() && $distanceSquared <= ($this->goalRadius + self::ARRIVAL_SLACK) ** 2;
			if (!$arrived) {
				$this->finished = $this->entity->getNavigation()->isDone(); //it can't get any closer, give up until the next search

				return;
			}
		}

		if ($this->eatTimer > 0) {
			--$this->eatTimer;

			return;
		}

		$world = $this->entity->getWorld();
		$block = $world->getBlock($targetPosition);
		$eaten = $this->getEaten($block);
		if ($eaten !== null) {
			$world->setBlock($targetPosition, $eaten);
		}
		$this->finished = true;
	}

	/**
	 * @return Block|null what the block becomes once eaten from, null if it can't be
	 */
	private function getEaten(Block $block) : ?Block{
		foreach ($this->eatables as $eatable) {
			$eaten = $eatable->getEaten($block);
			if ($eaten !== null) {
				return $eaten;
			}
		}

		return null;
	}
}
