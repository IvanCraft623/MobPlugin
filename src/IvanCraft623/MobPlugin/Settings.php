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

namespace IvanCraft623\MobPlugin;

use function array_key_exists;
use function file_exists;
use function is_array;
use function is_dir;
use function mkdir;
use function yaml_parse_file;
use const DIRECTORY_SEPARATOR;

final class Settings{

	private static Settings $globalSettings;

	/**
	 * @var ?Settings[]
	 * @phpstan-var array<string, ?Settings>
	 */
	private static array $settingsCache = [];

	public static function init() : void{
		MobPlugin::getInstance()->saveResource("global-settings.yml");

		self::$globalSettings = self::initSettings(MobPlugin::getInstance()->getDataFolder() . "global-settings.yml");

		$worldSettingsFolder = MobPlugin::getInstance()->getDataFolder() . "worlds-settings";
		if (!is_dir($worldSettingsFolder)) {
			mkdir($worldSettingsFolder);
		}
	}

	private static function initSettings(string $path) : Settings{
		if (!file_exists($path) || ($contents = yaml_parse_file($path)) === false || !is_array($contents)) {
			throw new \InvalidArgumentException("Settings file $path doesn't exist or is invalid");
		}

		return new Settings($contents);
	}

	public static function getGlobalSettings() : Settings{
		return self::$globalSettings;
	}

	public static function getSettings(string $worldFolderName) : Settings{
		if (array_key_exists($worldFolderName, self::$settingsCache)) {
			return self::$settingsCache[$worldFolderName] ?? self::$globalSettings;
		}

		$path = MobPlugin::getInstance()->getDataFolder() . "worlds-settings" . DIRECTORY_SEPARATOR . $worldFolderName . ".yml";
		if (file_exists($path)) {
			return self::$settingsCache[$worldFolderName] = self::initSettings($path);
		}

		self::$settingsCache[$worldFolderName] = null;
		return self::$globalSettings;
	}

	private bool $debugMode;

	private bool $mobNaturalDespawning;

	private bool $mobGriefing;

	/**
	 * @param mixed[] $data
	 * @phpstan-param array<string, mixed> $data
	 */
	private function __construct(array $data) {
		$this->debugMode = $this->getPropertyBool($data, "debug-mode", false);
		$this->mobNaturalDespawning = $this->getPropertyBool($data, "mob-natural-despawning", true);
		$this->mobGriefing = $this->getPropertyBool($data, "mob-griefing", true);
	}

	/**
	 * @param mixed[] $data
	 * @phpstan-param array<string, mixed> $data
	 */
	private function getPropertyBool(array $data, string $variable, bool $defaultValue) : bool{
		if (array_key_exists($variable, $data)) {
			return (bool) $data[$variable];
		}
		return $defaultValue;
	}

	public function isDebugModeEnabled() : bool{
		return $this->debugMode;
	}

	public function isMobNaturalDespawningEnabled() : bool{
		return $this->mobNaturalDespawning;
	}

	public function isMobGriefingEnabled() : bool{
		return $this->mobGriefing;
	}
}
