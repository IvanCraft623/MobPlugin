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

namespace IvanCraft623\MobPlugin\despawning;

use pocketmine\entity\Entity;
use pocketmine\nbt\tag\CompoundTag;

/**
 * Saves the persistent flag and the lifetime of the despawn profile with the entity. A
 * class with its own initEntity() or saveNBT() aliases the trait's and calls it in place
 * of the parent's.
 *
 * @phpstan-require-extends Entity
 */
trait DespawnPersistenceTrait{
	private const TAG_PERSISTENT = "Persistent"; //TAG_Byte
	private const TAG_LIFETIME = "Lifetime"; //TAG_Int

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$profile = DespawnRuleRegistry::getInstance()->getProfile($this);
		if($profile !== null){
			$profile->setPersistent($nbt->getByte(self::TAG_PERSISTENT, 0) !== 0);
			$profile->setLifetime($nbt->getInt(self::TAG_LIFETIME, 0));
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		//TODO: a type without a rule has no profile, so what it was loaded with is dropped
		$profile = DespawnRuleRegistry::getInstance()->getProfile($this);
		if($profile !== null){
			$nbt->setByte(self::TAG_PERSISTENT, $profile->isPersistent() ? 1 : 0);
			$nbt->setInt(self::TAG_LIFETIME, $profile->getLifetime());
		}

		return $nbt;
	}
}