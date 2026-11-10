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

namespace IvanCraft623\MobPlugin\spawning\event;

use IvanCraft623\MobPlugin\entity\aquatic\TropicalFishPreset;
use pocketmine\utils\SingletonTrait;

/**
 * @extends SpawnEventMap<TropicalFishPreset>
 */
final class TropicalFishPresetSpawnEventMap extends SpawnEventMap{
	use SingletonTrait;

	private function __construct(){
		foreach(TropicalFishPreset::cases() as $case){
			$this->register(match($case){
				TropicalFishPreset::ANEMONE => VanillaSpawnEvents::BECOME_ANENONME,
				TropicalFishPreset::CLOWNFISH => VanillaSpawnEvents::BECOME_CLOWNFISH,
				TropicalFishPreset::BLACK_TANG => VanillaSpawnEvents::BECOME_BLACK_TANG,
				TropicalFishPreset::CC_BETTA => VanillaSpawnEvents::BECOME_CC_BETTA,
				TropicalFishPreset::DOG_FISH => VanillaSpawnEvents::BECOME_DOG_FISH,
				TropicalFishPreset::BLUE_DORY => VanillaSpawnEvents::BECOME_BLUE_DORY,
				TropicalFishPreset::BUTTERFLY_FISH => VanillaSpawnEvents::BECOME_BUTTERFLY_FISH,
				TropicalFishPreset::CICHLID => VanillaSpawnEvents::BECOME_CICHLID,
				TropicalFishPreset::E_RED_SNAPPER => VanillaSpawnEvents::BECOME_E_RED_SNAPPER,
				TropicalFishPreset::GOAT_FISH => VanillaSpawnEvents::BECOME_GOAT_FISH,
				TropicalFishPreset::MOORISH_IDOL => VanillaSpawnEvents::BECOME_MOORISH_IDOL,
				TropicalFishPreset::ORNATE_BUTTERFLY => VanillaSpawnEvents::BECOME_ORNATE_BUTTERFLY,
				TropicalFishPreset::RED_SNAPPER => VanillaSpawnEvents::BECOME_RED_SNAPPER,
				TropicalFishPreset::PARROT_FISH => VanillaSpawnEvents::BECOME_PARROT_FISH,
				TropicalFishPreset::QUEEN_ANGEL_FISH => VanillaSpawnEvents::BECOME_QUEEN_ANGEL_FISH,
				TropicalFishPreset::RED_CICHLID => VanillaSpawnEvents::BECOME_RED_CICHLID,
				TropicalFishPreset::RED_LIPPED_BENNY => VanillaSpawnEvents::BECOME_RED_LIPPED_BENNY,
				TropicalFishPreset::THREADFIN => VanillaSpawnEvents::BECOME_THREADFIN,
				TropicalFishPreset::YELLOW_TANG => VanillaSpawnEvents::BECOME_YELLOW_TANG,
				TropicalFishPreset::TOMATO_CLOWN => VanillaSpawnEvents::BECOME_TOMATO_CLOWN,
				TropicalFishPreset::TRIGGERFISH => VanillaSpawnEvents::BECOME_TRIGGERFISH,
				TropicalFishPreset::YELLOW_TAIL_PARROT => VanillaSpawnEvents::BECOME_YELLOW_TAIL_PARROT,
			}, $case);
		}
	}
}
