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

namespace IvanCraft623\MobPlugin\spawning\spawner;

/**
 * Thrown by KeyContext when a condition reads a per-attempt value. Extends \Error so a
 * condition's catch(\Exception) can't swallow it.
 */
final class PointInputRequired extends \Error{
	private static ?self $point = null;

	private static ?self $population = null;

	private function __construct(
		string $message,
		private readonly bool $isPopulation
	){
		parent::__construct($message);
	}

	public static function point() : self{
		return self::$point ??= new self("Condition reads a per-attempt value", false);
	}

	public static function population() : self{
		return self::$population ??= new self("Condition reads the region population", true);
	}

	public function isPopulation() : bool{
		return $this->isPopulation;
	}
}
