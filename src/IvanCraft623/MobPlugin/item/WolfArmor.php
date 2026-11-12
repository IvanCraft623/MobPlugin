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

namespace IvanCraft623\MobPlugin\item;

use IvanCraft623\MobPlugin\entity\Crackiness;
use pocketmine\item\Durable;

/**
 * Armor for a wolf's body.
 */
class WolfArmor extends Durable{

	public const MAX_DURABILITY = 64;

	//Durability left at which each crack stage starts
	private const LOW_CRACKS_DURABILITY = 60;
	private const MEDIUM_CRACKS_DURABILITY = 44;
	private const HIGH_CRACKS_DURABILITY = 20;

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getMaxDurability() : int{
		return self::MAX_DURABILITY;
	}

	public function getCrackiness() : Crackiness{
		$remaining = $this->getMaxDurability() - $this->getDamage();

		return match(true){
			$remaining <= self::HIGH_CRACKS_DURABILITY => Crackiness::HIGH,
			$remaining <= self::MEDIUM_CRACKS_DURABILITY => Crackiness::MEDIUM,
			$remaining <= self::LOW_CRACKS_DURABILITY => Crackiness::LOW,
			default => Crackiness::NONE
		};
	}
}
