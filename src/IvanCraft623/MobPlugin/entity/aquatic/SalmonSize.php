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

use function mt_rand;

enum SalmonSize{
	case SMALL;
	case NORMAL;
	case LARGE;

	public function getScale() : float{
		return match($this){
			self::SMALL => 0.5,
			self::NORMAL => 1.0,
			self::LARGE => 1.5
		};
	}

	/**
	 * The component group vanilla saves the size as.
	 */
	public function getComponentGroup() : string{
		return match($this){
			self::SMALL => "scale_small",
			self::NORMAL => "scale_normal",
			self::LARGE => "scale_large"
		};
	}

	/**
	 * Natural spawn odds: 30 small, 50 normal, 15 large, and 5 left without a size, which is a normal one.
	 */
	public static function random() : self{
		$roll = mt_rand(0, 99);
		return match(true){
			$roll < 30 => self::SMALL,
			$roll < 45 => self::LARGE,
			default => self::NORMAL
		};
	}
}
