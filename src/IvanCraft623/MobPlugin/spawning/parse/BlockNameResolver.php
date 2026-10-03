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

namespace IvanCraft623\MobPlugin\spawning\parse;

use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use function str_starts_with;
use function strlen;
use function substr;

final class BlockNameResolver{
	private const PREFIX = "minecraft:";

	/**
	 * Bedrock block names PocketMine registers under a different key.
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = [
		"minecraft:grass_block" => "minecraft:grass",
		"minecraft:clay" => "minecraft:clay_block",
		"minecraft:brown_terracotta" => "minecraft:brown_stained_clay",
		"minecraft:light_gray_terracotta" => "minecraft:light_gray_stained_clay",
		"minecraft:orange_terracotta" => "minecraft:orange_stained_clay",
		"minecraft:red_terracotta" => "minecraft:red_stained_clay",
		"minecraft:white_terracotta" => "minecraft:white_stained_clay",
		"minecraft:yellow_terracotta" => "minecraft:yellow_stained_clay",
	];

	/**
	 * The item state id of the named block: its type plus the properties that make it a
	 * distinct block (dirt type, colour), but not placement state like facing or snow layers.
	 */
	public function resolve(string $name) : ?int{
		$itemStateId = self::parse($name);
		if($itemStateId === null && isset(self::ALIASES[$name])){
			$itemStateId = self::parse(self::ALIASES[$name]);
		}

		return $itemStateId;
	}

	private static function parse(string $name) : ?int{
		$localName = str_starts_with($name, self::PREFIX) ? substr($name, strlen(self::PREFIX)) : $name;
		$parsed = StringToItemParser::getInstance()->parse($localName);

		return $parsed instanceof ItemBlock ? $parsed->getStateId() : null;
	}
}
