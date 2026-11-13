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

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\memory\Memories;
use IvanCraft623\MobPlugin\memory\Memory;
use IvanCraft623\MobPlugin\memory\Occurrence;
use pocketmine\entity\Living;

class HurtByTargetGoal extends TargetGoal {

	public const ALERT_RANGE_Y = 10;

	public static function HURT_BY_TARGETING() : TargetingConditions{
		return (new TargetingConditions())
			->allowUnseeable()
			->testInvisible(false);
	}

	private TargetingConditions $targetingConditions;

	private bool $alertSameType = false;

	private int $lastDamageTick = -1;

	private ?Memory $pending = null; //the hit that is about to start

	/** @phpstan-var class-string<Living>[] */
	private array $ignoredDamagers = [];

	/** @phpstan-var class-string<Living>[] */
	private array $ignoredAlert = [];

	/**
	 * @phpstan-param class-string<Living> $ignoredDamagers
	 */
	public function __construct(Mob $entity, string ...$ignoredDamagers) {
		parent::__construct($entity, true);

		$this->ignoredDamagers = $ignoredDamagers;
		$this->targetingConditions = self::HURT_BY_TARGETING();

		$this->setFlags(Goal::FLAG_TARGET);
	}

	public function getTargetingConditions() : TargetingConditions{
		return $this->targetingConditions;
	}

	/**
	 * @phpstan-param class-string<Living> $ignoredAlert
	 *
	 * @return $this
	 */
	public function setAlertOthers(string ...$ignoredAlert) : self{
		$this->alertSameType = true;
		$this->ignoredAlert = $ignoredAlert;

		return $this;
	}

	public function canUse() : bool{
		$memory = Memories::getInstance()->latest($this->entity, Occurrence::HURT_BY, Mob::HURT_BY_MEMORY_TICKS);
		if ($memory === null || $memory->getTick() === $this->lastDamageTick) {
			return false;
		}

		$damager = $memory->getEntity();
		if (!$damager instanceof Living) {
			return false;
		}

		foreach ($this->ignoredDamagers as $ignoredDamager) {
			if ($damager instanceof $ignoredDamager) {
				return false;
			}
		}
		if (!$this->canAttack($damager, $this->targetingConditions)) {
			return false;
		}

		$this->target = $damager;
		$this->pending = $memory;

		return true;
	}

	public function start() : void{
		/** @var Living $target*/
		$target = $this->target;

		/** @var Memory $hit */
		$hit = $this->pending;

		$this->entity->setTargetEntity($target);
		$this->lastDamageTick = $hit->getTick();
		$this->unseenMemoryTicks = 300;

		if ($this->alertSameType) {
			$this->alertOthers($target);
		}

		parent::start();
	}

	protected function alertOthers(Living $target) : void{
		foreach ($this->entity->getSensing()->getNearbyLiving($this->getFollowDistance(), self::ALERT_RANGE_Y) as $other) {
			if ($other instanceof $this->entity &&
				$other->getTargetEntityId() === null &&
				!$this->shouldIgnoreAlert($other)
			) { //TODO: Tamable entities check if this and other entities are not from the same owner
				$this->alertOther($other, $target);
			}
		}
	}

	private function shouldIgnoreAlert(Mob $entity) : bool{
		foreach ($this->ignoredAlert as $ignored) {
			if ($entity instanceof $ignored) {
				return true;
			}
		}
		return false;
	}

	protected function alertOther(Mob $entity, Living $target) : void{
		$entity->setTargetEntity($target);
	}
}
