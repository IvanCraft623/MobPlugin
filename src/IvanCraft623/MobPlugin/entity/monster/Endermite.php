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

namespace IvanCraft623\MobPlugin\entity\monster;

use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\MobType;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

class Endermite extends Monster {

	private const TAG_LIFE = "Lifetime";

	public const MAX_LIFE = 2400;

	public static function getNetworkTypeId() : string{ return EntityIds::ENDERMITE; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.3, 0.4);
	}

	public function getName() : string{
		return "Endermite";
	}

	public function getMobType() : MobType{
		return MobType::ARTHROPOD();
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new FloatGoal($this));
		//TODO: powder snow climb goal
		$this->goalSelector->addGoal(2, new MeleeAttackGoal($this, 1, false));
		$this->goalSelector->addGoal(3, new WaterAvoidingRandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(7, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(8, new RandomLookAroundGoal($this));

		//TODO: hurt by target goal
		$this->targetSelector->addGoal(2, new NearestAttackableGoal($this, Player::class));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->ticksLived = $nbt->getInt(self::TAG_LIFE, 0);

		parent::initEntity($nbt);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_LIFE, $this->ticksLived);

		return $nbt;
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(8);
		$this->setMovementSpeed(0.25);
		$this->setAttackDamage(2);
	}

	public function getMaxLifeTime() : int{
		return self::MAX_LIFE;
	}

	//TODO: spawn rules code
}
