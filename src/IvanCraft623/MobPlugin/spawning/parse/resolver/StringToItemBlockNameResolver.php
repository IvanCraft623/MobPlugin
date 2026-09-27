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

namespace IvanCraft623\MobPlugin\spawning\parse\resolver;

use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use function str_starts_with;
use function strlen;
use function substr;

/** Resolves through PocketMine's StringToItemParser after stripping the "minecraft:" prefix. */
final class StringToItemBlockNameResolver implements BlockNameResolver{
	private const PREFIX = "minecraft:";

	public function resolve(string $name) : ?int{
		$localName = str_starts_with($name, self::PREFIX) ? substr($name, strlen(self::PREFIX)) : $name;
		$parsed = StringToItemParser::getInstance()->parse($localName);

		return $parsed instanceof ItemBlock ? $parsed->getBlock()->getTypeId() : null;
	}
}
