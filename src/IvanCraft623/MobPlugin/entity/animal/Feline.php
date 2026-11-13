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

namespace IvanCraft623\MobPlugin\entity\animal;

use IvanCraft623\MobPlugin\entity\ai\goal\AvoidEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\feline\StalkAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LeapAtTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;

/**
 * What cats and ocelots share: stalking, no fall damage, tempting with fish and wariness of players.
 */
abstract class Feline extends Animal{

	/** Fish, which breeds and grows felines. */
	private const FOOD = [
		ItemTypeIds::RAW_FISH => true,
		ItemTypeIds::RAW_SALMON => true,
	];

	/**
	 * Whether it avoids players and gets scared by them.
	 */
	abstract public function isWary() : bool;

	public function getDefaultMovementSpeed() : float{
		return 0.3;
	}

	public function isFood(Item $item) : bool{
		return isset(self::FOOD[$item->getTypeId()]);
	}

	public function attack(EntityDamageEvent $source) : void{
		if ($source->getCause() === EntityDamageEvent::CAUSE_FALL) {
			$source->cancel();
		}

		parent::attack($source);
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setScale($this->getAdultScale());
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 1.25));
		$this->goalSelector->addGoal(3, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new LeapAtTargetGoal($this, 0.3));
		$this->goalSelector->addGoal(4, new StalkAttackGoal($this, 0.8, 0.6, 1.33, 4, 15));
		$this->goalSelector->addGoal(5, new class($this) extends TemptGoal{
			public function __construct(private Feline $feline){
				parent::__construct($feline, 0.5, false);
			}

			protected function canScare() : bool{
				return $this->feline->isWary();
			}
		});
		$this->goalSelector->addGoal(6, new class($this) extends AvoidEntityGoal{
			public function __construct(private Feline $feline){
				parent::__construct($feline, [Player::class], 10, 0.8, 1.33);
			}

			public function canUse() : bool{
				return $this->feline->isWary() && parent::canUse();
			}
		});
		$this->goalSelector->addGoal(8, new WaterAvoidingRandomStrollGoal($this, 0.8));
		$this->goalSelector->addGoal(9, new LookAtEntityGoal($this, Player::class, 8));
	}
}