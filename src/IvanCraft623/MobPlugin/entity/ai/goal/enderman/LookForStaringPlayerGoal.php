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

namespace IvanCraft623\MobPlugin\entity\ai\goal\enderman;

use IvanCraft623\MobPlugin\entity\ai\goal\target\TargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\monster\Enderman;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;

use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\entity\Living as PMLiving;

use Closure;

class LookForStaringPlayerGoal extends TargetGoal {

	protected int $aggroCooldownTicks = 0;

	protected TargetingConditions $startAggroTargetConditions;

	public function __construct(
		protected Enderman $enderman,
		?TargetingConditions $startAggroTargetConditions = null
	) {
		parent::__construct($enderman, false, false);

		$this->startAggroTargetConditions = $startAggroTargetConditions ?? (new TargetingConditions())
			->allowUnseeable()
			->testInvisible(false)
			->setRange($this->getFollowDistance());
	}

	public function canUse() : bool{
		if ($this->aggroCooldownTicks > 0) {
			$this->aggroCooldownTicks--;
			return false;
		}

		$this->findTarget();
		return $this->target !== null;
	}

	protected function findTarget() : void{
		$pos = $this->enderman->getLocation();
		$this->target = array_reduce($this->enderman->getWorld()->getCollidingEntities($this->getTargetSearchArea($this->getFollowDistance()), $this->enderman),
		function(?Player $carry, Entity $current) use ($pos) : ?Player {
			if (!$current instanceof Player ||
				!$this->isAngerTriggering($current) ||
				!$this->canAttack($current, $this->startAggroTargetConditions)
			) {
				return $carry;
			}

			return ($carry !== null &&
				$carry->getPosition()->distanceSquared($pos) < $current->getPosition()->distanceSquared($pos)
			) ? $carry : $current;
		}, null);
	}

	public function getTargetSearchArea(float $range) : AxisAlignedBB{
		return $this->enderman->getBoundingBox()->expandedCopy($range, $range, $range);
	}

	public function isAngerTriggering(Player $player) : bool{
		return $this->enderman->isLookingAtMe($player);
	}

	public function start() : void{
		$this->aggroCooldownTicks = $this->adjustedTickDelay(100);
		$this->enderman->setTargetEntity($this->target);
		$this->enderman->onBeingStaredAt();

		parent::start();
	}
}
