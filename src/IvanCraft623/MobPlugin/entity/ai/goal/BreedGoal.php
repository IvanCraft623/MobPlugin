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

use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\animal\Animal;

use pocketmine\entity\Living;

class BreedGoal extends Goal {

	public static function PARTNER_TARGETING() : TargetingConditions{
		return (new TargetingConditions())
			->setRange(self::PARTNER_SEARCH_RANGE)
			->allowNonAttackable()
			->allowUnseeable();
	}

	public const PARTNER_SEARCH_RANGE = 8;

	protected TargetingConditions $partnerConditions;

	/** @phpstan-var class-string<Animal> */
	private string $partnerClass;

	protected Animal $partner;

	protected int $loveTicks = 0;

	/**
	 * @phpstan-param ?class-string<Animal> $partnerClass
	 */
	public function __construct(
		protected Animal $entity,
		protected float $speedModifier,
		?string $partnerClass = null
	) {
		$this->partnerClass = $partnerClass = $partnerClass ?? $entity::class;
		$this->partnerConditions = self::PARTNER_TARGETING()->setValidator(static function(Living $target) use ($entity, $partnerClass) : bool{
			return $target instanceof $partnerClass && $entity->canMate($target);
		});

		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		if (!$this->entity->isInLove()) {
			return false;
		}

		if (($partner = $this->getFreePartner()) !== null) {
			$this->partner = $partner;
			return true;
		}

		return false;
	}

	public function canContinueToUse() : bool{
		return $this->partner->isAlive() && $this->partner->isInLove() && $this->loveTicks < 60;
	}

	public function stop() : void{
		unset($this->partner);
		$this->loveTicks = 0;
	}

	public function tick() : void{
		$this->entity->getLookControl()->setLookAt($this->partner, 10);
		$this->entity->getNavigation()->moveToEntity($this->partner, $this->speedModifier);

		$this->loveTicks++;
		if ($this->loveTicks >= 60 && $this->entity->getLocation()->distanceSquared($this->partner->getLocation()) < 9) {
			$this->bread();
		}
	}

	public function getFreePartner() : ?Animal {
		return $this->entity->getSensing()->findNearest(
			$this->partnerClass,
			self::PARTNER_SEARCH_RANGE,
			conditions: $this->partnerConditions
		);
	}

	public function bread() : void{
		$this->entity->spawnChildFromBreeding($this->partner);
	}
}
