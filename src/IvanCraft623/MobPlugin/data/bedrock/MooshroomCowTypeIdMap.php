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

use IvanCraft623\MobPlugin\entity\animal\MooshroomCowType;
use pocketmine\data\bedrock\IntSaveIdMapTrait;
use pocketmine\utils\SingletonTrait;

final class MooshroomCowTypeIdMap{
	use SingletonTrait;
	/** @phpstan-use IntSaveIdMapTrait<MooshroomCowType> */
	use IntSaveIdMapTrait;

	private function __construct(){
		foreach(MooshroomCowType::cases() as $case){
			$this->register(match($case){
				MooshroomCowType::RED => MooshroomCowTypeIds::RED,
				MooshroomCowType::BROWN => MooshroomCowTypeIds::BROWN
			}, $case);
		}
	}
}
