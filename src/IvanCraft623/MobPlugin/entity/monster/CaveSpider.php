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

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\World;

class CaveSpider extends Spider {

	/**
	 * Returns the poison effect duration in ticks applied in attacks
	 */
	public static function getPoisonEffectDuration(int $difficulty) : int{
		return match($difficulty) {
			World::DIFFICULTY_NORMAL => 7, //seconds
			World::DIFFICULTY_HARD => 15, //seconds
			default => 0
		} * 20;
	}

	public static function getNetworkTypeId() : string{ return EntityIds::CAVE_SPIDER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.5, 0.7, 0.45);
	}

	public function getName() : string{
		return "Cave Spider";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(12);
	}

	public function attackEntity(Entity $entity) : bool{
		if (parent::attackEntity($entity)) {
			if ($entity instanceof Living &&
				($duration = static::getPoisonEffectDuration($this->getWorld()->getDifficulty())) > 0
			) {
				$entity->getEffects()->add(new EffectInstance(VanillaEffects::POISON(), $duration));
			}
			return true;
		}

		return false;
	}
}
