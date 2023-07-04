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
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\AxisAlignedBB;

use pocketmine\utils\Utils;

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

	/**
	 * @phpstan-var class-string<Living>[]
	 */
	private array $ignoredDamagers = [];

	/**
	 * @phpstan-var class-string<Living>[]
	 */
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
		$lastDamageTick = $this->entity->getLastDamageByEntityTick();
		$lastDamage = $this->entity->getLastDamageByEntity();
		if ($lastDamageTick !== $this->lastDamageTick && $lastDamage !== null) {
			$damager = $lastDamage->getDamager();
			if (!$damager instanceof Living) {
				return false;
			}

			foreach ($this->ignoredDamagers as $ignoredDamager) {
				if ($damager instanceof $ignoredDamager) {
					return false;
				}
			}
			return $this->canAttack($damager, $this->targetingConditions);
		}
		return false;
	}

	public function start() : void{
		/** @var EntityDamageByEntityEvent $lastDamage*/
		$lastDamage = $this->entity->getLastDamageByEntity();

		/** @var Living $target*/
		$target = $lastDamage->getDamager();

		$this->entity->setTargetEntity($target);
		$this->target = $target;
		$this->lastDamageTick = $this->entity->getLastDamageByEntityTick();
		$this->unseenMemoryTicks = 300;

		if ($this->alertSameType) {
			$this->alertOthers($target);
		}

		parent::start();
	}

	public function getAlertOthersArea(float $range) : AxisAlignedBB{
		return $this->entity->getBoundingBox()->expandedCopy($range, self::ALERT_RANGE_Y, $range);
	}

	protected function alertOthers(Living $target) : void{
		foreach ($this->entity->getWorld()->getCollidingEntities($this->getAlertOthersArea($this->getFollowDistance()), $this->entity) as $other) {
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
