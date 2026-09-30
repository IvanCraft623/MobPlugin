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

namespace IvanCraft623\MobPlugin\tools\spawnrules;

use Composer\InstalledVersions;
use function realpath;

/**
 * The pinned mojang/bedrock-samples dev dependency. composer.json is the only place its
 * commit and spawn schema version (the package version) are declared.
 */
final class BedrockSamples{
	public const PACKAGE = "mojang/bedrock-samples";

	public static function getSchemaVersion() : string{
		return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? throw new \RuntimeException(self::PACKAGE . " has no version; run composer install");
	}

	public static function getReference() : ?string{
		return InstalledVersions::getReference(self::PACKAGE);
	}

	public static function getInstallPath() : string{
		$path = InstalledVersions::getInstallPath(self::PACKAGE);
		$resolved = $path !== null ? realpath($path) : false;

		return $resolved !== false ? $resolved : throw new \RuntimeException(self::PACKAGE . " is not installed; run composer install");
	}
}
