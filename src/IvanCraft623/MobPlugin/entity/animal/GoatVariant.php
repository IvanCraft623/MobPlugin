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

use pocketmine\item\GoatHornType;
use function spl_object_id;

/**
 * @phpstan-type TData array{0: list<GoatHornType>, 1: int, 2: int}
 */
enum GoatVariant{
	case NORMAL;
	case SCREAMER;

	/**
	 * Horn tunes, and the least and most ticks between rams.
	 *
	 * @phpstan-return TData
	 */
	private function getData() : array{
		/** @phpstan-var array<int, TData> $cache */
		static $cache = [];

		return $cache[spl_object_id($this)] ??= match($this){
			self::NORMAL => [
				[GoatHornType::PONDER, GoatHornType::SING, GoatHornType::SEEK, GoatHornType::FEEL],
				600,
				6000
			],
			self::SCREAMER => [
				[GoatHornType::ADMIRE, GoatHornType::CALL, GoatHornType::YEARN, GoatHornType::DREAM],
				100,
				300
			],
		};
	}

	/**
	 * @return list<GoatHornType>
	 */
	public function getHornTypes() : array{ return $this->getData()[0]; }

	/**
	 * @return int least ticks between rams
	 */
	public function getMinRamCooldown() : int{ return $this->getData()[1]; }

	/**
	 * @return int most ticks between rams
	 */
	public function getMaxRamCooldown() : int{ return $this->getData()[2]; }
}