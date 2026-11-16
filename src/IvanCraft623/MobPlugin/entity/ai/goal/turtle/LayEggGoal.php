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

namespace IvanCraft623\MobPlugin\entity\ai\goal\turtle;

use IvanCraft623\MobPlugin\block\ExtraVanillaBlocks;
use IvanCraft623\MobPlugin\block\TurtleEgg;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\utils\NearestBlockFinder;
use IvanCraft623\MobPlugin\entity\animal\Turtle;
use IvanCraft623\MobPlugin\sound\TurtleLayEggSound;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Vector3;
use pocketmine\world\particle\BlockBreakParticle;

/**
 * Digs a nest in a sand block and lays its eggs on top of it.
 */
final class LayEggGoal extends Goal{

	private const DIG_PARTICLE_INTERVAL = 5; //ticks between digging particles

	private Vector3 $nest;

	private int $layTicks = 0; //ticks spent digging

	/**
	 * @param int   $searchRange  blocks around it to look for sand
	 * @param int   $searchHeight blocks above and below it to look for sand
	 * @param float $goalRadius   blocks from the nest that count as being at it
	 * @param int   $layDuration  ticks of digging before the eggs are laid
	 */
	public function __construct(
		private Turtle $turtle,
		private float $speedModifier,
		private int $searchRange,
		private int $searchHeight,
		private float $goalRadius,
		private int $layDuration
	){
		$this->setFlags(Goal::FLAG_MOVE);
	}

	public function canUse() : bool{
		if (
			!$this->turtle->wantsToLayEgg() ||
			//average ticks between searches for a nest
			$this->turtle->getRandom()->nextBoundedInt($this->reducedTickDelay(10)) !== 0
		) {
			return false;
		}

		$nest = $this->findNest();
		if ($nest === null) {
			return false;
		}
		$this->nest = $nest;

		return true;
	}

	public function canContinueToUse() : bool{
		return $this->turtle->wantsToLayEgg() && $this->isValidNest($this->nest);
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->layTicks = 0;
		$this->moveToNest();
	}

	private function moveToNest() : void{
		$this->turtle->getNavigation()->moveToXYZ($this->nest->x + 0.5, $this->nest->y + 1, $this->nest->z + 0.5, $this->speedModifier);
	}

	public function stop() : void{
		$this->turtle->getNavigation()->stop();
		$this->turtle->setLayingEgg(false);
	}

	public function tick() : void{
		if ($this->turtle->getPosition()->distanceSquared($this->nest->add(0.5, 1, 0.5)) > $this->goalRadius ** 2) {
			//Not there yet, or it slid off the nest
			$this->turtle->setLayingEgg(false);
			if ($this->turtle->getNavigation()->isDone()) {
				$this->moveToNest();
			}

			return;
		}

		$this->turtle->getNavigation()->stop();
		$this->turtle->setLayingEgg(true);
		$tick = $this->layTicks++;

		$world = $this->turtle->getWorld();
		if ($tick % $this->reducedTickDelay(self::DIG_PARTICLE_INTERVAL) === 0) {
			$world->addParticle($this->nest->add(0.5, 0.5, 0.5), new BlockBreakParticle($world->getBlock($this->nest)));
		}
		if ($tick >= $this->layDuration) {
			$this->layEgg();
		}
	}

	private function layEgg() : void{
		$world = $this->turtle->getWorld();
		$position = $this->nest->up();

		$egg = ExtraVanillaBlocks::TURTLE_EGG()->setCount(
			$this->turtle->getRandom()->nextRange(TurtleEgg::MIN_COUNT, TurtleEgg::MAX_COUNT)
		);
		$world->setBlock($position, $egg);
		$world->addSound($position->add(0.5, 0.5, 0.5), new TurtleLayEggSound());

		$this->turtle->finishLayingEgg();
	}

	private function findNest() : ?Vector3{
		foreach ([BlockTypeIds::SAND, BlockTypeIds::RED_SAND] as $typeId) {
			foreach (NearestBlockFinder::find(
				$this->turtle->getWorld(),
				$this->turtle->getPosition(),
				$this->searchRange,
				$this->searchHeight,
				$typeId
			) as $sand) {
				if ($this->isValidNest($sand)) {
					return $sand;
				}
			}
		}

		return null;
	}

	/**
	 * Whether the sand block has room above it for the eggs.
	 */
	private function isValidNest(Vector3 $sand) : bool{
		$world = $this->turtle->getWorld();

		return isset(Turtle::NESTING_BLOCKS[$world->getBlock($sand)->getTypeId()]) &&
			$world->getBlock($sand->up())->getTypeId() === BlockTypeIds::AIR;
	}
}