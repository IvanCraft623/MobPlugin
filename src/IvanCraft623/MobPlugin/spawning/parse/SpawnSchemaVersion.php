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

use function array_keys;
use function implode;

/**
 * Version seam for the vanilla spawn-rule format. The compiled resource spans several
 * vanilla schema versions (1.8.0, 1.11.0, 1.17.0) whose shapes this loader parses
 * identically; an unknown version aborts the load instead of silently mis-parsing.
 */
final class SpawnSchemaVersion{
	/**
	 * Versions present in the compiled resource. Bump/extend when the resource is
	 * recompiled against newer bedrock-samples.
	 *
	 * @var array<string, true>
	 */
	public const SUPPORTED = ["1.8.0" => true, "1.11.0" => true, "1.17.0" => true];

	/**
	 * @phpstan-throws SpawnModelParseException on an unsupported version
	 */
	public static function assertSupported(string $version, string $identifier) : void{
		if(!isset(self::SUPPORTED[$version])){
			throw new SpawnModelParseException("Spawn rules for \"$identifier\": unsupported format_version \"$version\"; supported versions: " . implode(", ", array_keys(self::SUPPORTED)) . ".");
		}
	}
}
