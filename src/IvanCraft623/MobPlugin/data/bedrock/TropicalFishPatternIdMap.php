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

namespace IvanCraft623\MobPlugin\data\bedrock;

use IvanCraft623\MobPlugin\entity\aquatic\TropicalFishPattern;
use pocketmine\data\bedrock\IntSaveIdMapTrait;
use pocketmine\utils\SingletonTrait;

final class TropicalFishPatternIdMap{
	use SingletonTrait;
	/** @phpstan-use IntSaveIdMapTrait<TropicalFishPattern> */
	use IntSaveIdMapTrait;

	private function __construct(){
		foreach(TropicalFishPattern::cases() as $case){
			$this->register(match($case){
				TropicalFishPattern::PATTERN_0 => TropicalFishPatternIds::PATTERN_0,
				TropicalFishPattern::PATTERN_1 => TropicalFishPatternIds::PATTERN_1,
				TropicalFishPattern::PATTERN_2 => TropicalFishPatternIds::PATTERN_2,
				TropicalFishPattern::PATTERN_3 => TropicalFishPatternIds::PATTERN_3,
				TropicalFishPattern::PATTERN_4 => TropicalFishPatternIds::PATTERN_4,
				TropicalFishPattern::PATTERN_5 => TropicalFishPatternIds::PATTERN_5
			}, $case);
		}
	}
}
