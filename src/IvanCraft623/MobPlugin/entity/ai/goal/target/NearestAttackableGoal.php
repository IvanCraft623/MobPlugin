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

namespace IvanCraft623\MobPlugin\entity\ai\goal\target;

use Closure;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;

use IvanCraft623\MobPlugin\entity\Mob;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;

use pocketmine\utils\Utils;
use function array_reduce;

class NearestAttackableGoal extends TargetGoal {

	public const DEFAULT_RANDOM_INTERVAL = 10;

	protected int $randomInterval;

	protected TargetingConditions $targetingConditions;

	/**
	 * @phpstan-param class-string<Living> $targetType
	 */
	public function __construct(
		Mob $entity,
		protected string $targetType,
		int $interval = self::DEFAULT_RANDOM_INTERVAL,
		bool $mustSee = true,
		bool $mustReach = false,
		?Closure $targetValidator = null
	) {
		Utils::testValidInstance($targetType, Living::class);

		parent::__construct($entity, $mustSee, $mustReach);

		$this->randomInterval = $this->reducedTickDelay($interval);
		$this->targetingConditions = (new TargetingConditions())
			->setRange($this->getFollowDistance())
			->setValidator($targetValidator);

		$this->setFlags(Goal::FLAG_TARGET);
	}

	public function canUse() : bool{
		if ($this->randomInterval > 0 && $this->entity->getRandom()->nextBoundedInt($this->randomInterval) !== 0) {
			return false;
		}

		$this->findTarget();
		return $this->target !== null;
	}

	public function getTargetSearchArea(float $range) : AxisAlignedBB{
		return $this->entity->getBoundingBox()->expandedCopy($range, 0.4, $range);
	}

	protected function findTarget() : void{
		$pos = $this->entity->getEyePos();
		$this->target = array_reduce($this->entity->getWorld()->getCollidingEntities($this->getTargetSearchArea($this->getFollowDistance()), $this->entity),
		function(?Living $carry, Entity $current) use ($pos) : ?Living {
			if (!$current instanceof $this->targetType || !$this->targetingConditions->test($this->entity, $current)) {
				return $carry;
			}

			return ($carry !== null &&
				$carry->getPosition()->distanceSquared($pos) < $current->getPosition()->distanceSquared($pos)
			) ? $carry : $current;
		}, null);
	}

	public function start() : void{
		$this->entity->setTargetEntity($this->target);
		parent::start();
	}

	public function setTarget(?Living $target) : void{
		$this->target = $target;
	}
}
