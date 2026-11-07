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

namespace IvanCraft623\MobPlugin\entity\aquatic;

use IvanCraft623\MobPlugin\entity\ai\goal\AvoidEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowFlockGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\ManageFlockGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomSwimmingGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\SwimIdleGoal;
use IvanCraft623\MobPlugin\entity\ai\navigation\locomotion\SwimLocomotion;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\animal\Axolotl;
use IvanCraft623\MobPlugin\entity\Bucketable;
use IvanCraft623\MobPlugin\entity\BucketableTrait;
use IvanCraft623\MobPlugin\entity\Flocking;
use IvanCraft623\MobPlugin\entity\FlockingTrait;
use IvanCraft623\MobPlugin\entity\PathfinderMob;

use IvanCraft623\Pathfinder\BlockPathType;

use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function mt_rand;

/**
 * A fish: stays in water, swims in flocks of its kind and flees players and axolotls.
 */
abstract class AbstractFish extends PathfinderMob implements Bucketable, Flocking {
	use BucketableTrait;
	use FlockingTrait;

	private const AVOID_SPEED = 1.5;
	private const AVOID_SPRINT_SPEED = 2;

	/**
	 * Distance, in blocks, at which nearby threats make the fish flee.
	 */
	abstract protected function getAvoidDistance() : float;

	public function getDefaultMovementSpeed() : float{
		return 0.12;
	}

	public function getXpDropAmount() : int{
		return $this->hasBeenDamagedByPlayer() ? mt_rand(1, 3) : 0;
	}

	public function getWaterMovementSpeed() : float{
		return $this->getDefaultMovementSpeed();
	}

	public function getWalkTargetValue(Vector3 $position) : float{
		return 0;
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(3);
		$this->setHasGravity(false);

		$this->navigation->setPathfindingMalus(BlockPathType::WATER, 0);
		$this->navigation->setPathfindingMalus(BlockPathType::WATER_BORDER, 0);

	}

	public function createNavigation() : PathNavigation{
		return new PathNavigation($this, new SwimLocomotion(gentleVertical: false));
	}

	protected function registerGoals() : void{
		$this->initFlocking();

		$this->goalSelector->addGoal(0, new ManageFlockGoal($this));
		//Above the flee goal: a school flees with its leader instead of scattering
		$this->goalSelector->addGoal(1, new FollowFlockGoal($this));
		$this->goalSelector->addGoal(1, new AvoidEntityGoal(
			$this,
			[Player::class, Axolotl::class],
			$this->getAvoidDistance(),
			self::AVOID_SPEED,
			self::AVOID_SPRINT_SPEED,
			stayInWater: true
		));
		$this->goalSelector->addGoal(3, new RandomSwimmingGoal($this, 1, 0, 16, 4, avoidSurface: true));
		$this->goalSelector->addGoal(5, new SwimIdleGoal($this, 100, 0.05));
	}

	/**
	 * Fish can't breathe air: they dry out on land.
	 */
	public function canBreathe() : bool{
		return $this->isInWater();
	}

	public function canFlockNow() : bool{
		return $this->isInWater();
	}

	public function canFlockThrough(Block $block) : bool{
		return $block instanceof Water;
	}
}
